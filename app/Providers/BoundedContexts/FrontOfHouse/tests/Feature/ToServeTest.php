<?php

use App\Providers\BoundedContexts\FrontOfHouse\Application\PassRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePass;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Projection\ToServeProjector;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use Illuminate\Support\Facades\DB;

describe('the pass', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->postJson('/api/front-of-house/tables/table-4/orders', [
            'lines' => [
                ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
            ],
        ])->assertOk();
    });

    test('the pass stays empty until the queued projector runs', function () {
        config(['queue.default' => 'database']);

        $screen = $this->getJson('/api/kitchen/screen')->json();
        $ticketId = $screen[0]['ticketId'];
        $lineId = $screen[0]['lines'][0]['lineId'];

        $this->postJson("/api/kitchen/tickets/{$ticketId}/fire", ['course' => 'starter'])->assertOk();
        $this->postJson("/api/kitchen/tickets/{$ticketId}/lines/{$lineId}/ready")->assertOk();

        $this->getJson('/api/front-of-house/to-serve')->assertExactJson([]);

        $this->artisan('queue:work', ['--stop-when-empty' => true, '--memory' => 2048])->assertSuccessful();

        $waiting = $this->getJson('/api/front-of-house/to-serve')->assertOk()->json();
        expect($waiting)->toHaveCount(1)
            ->and($waiting[0]['dishName'])->toBe('Green salad')
            ->and($waiting[0]['tableId'])->toBe('table-4')
            ->and($waiting[0]['quantity'])->toBe(1)
            ->and($waiting[0]['tableOrderId'])->toBe($ticketId)
            ->and($waiting[0])->not->toHaveKey('ticketId');
    });

    test('serving a dish acknowledges the command and leaves the pass', function () {
        $screen = $this->getJson('/api/kitchen/screen')->json();
        $ticketId = $screen[0]['ticketId'];
        $lineId = $screen[0]['lines'][0]['lineId'];
        $this->postJson("/api/kitchen/tickets/{$ticketId}/fire", ['course' => 'starter'])->assertOk();
        $this->postJson("/api/kitchen/tickets/{$ticketId}/lines/{$lineId}/ready")->assertOk();

        $toServeId = $this->getJson('/api/front-of-house/to-serve')->json()[0]['id'];

        $this->postJson("/api/front-of-house/to-serve/{$toServeId}/serve")
            ->assertExactJson(['toServeId' => $toServeId, 'accepted' => true]);

        $this->getJson('/api/front-of-house/to-serve')->assertExactJson([]);

        $served = collect(app(EventStore::class)->readAllFrom(0))
            ->first(fn ($event) => $event->type->is('front_of_house.dish_served'));

        expect($served->payload)->toMatchArray([
            'tableId' => 'table-4',
            'description' => 'Green salad',
            'quantity' => 1,
            'unitPriceCents' => 1100,
            'kind' => 'food',
        ]);

        $this->postJson("/api/front-of-house/to-serve/{$toServeId}/serve")
            ->assertUnprocessable()
            ->assertJson(['message' => 'That dish has already left the pass.']);
    });

    test('the to-serve query does not load a pass aggregate', function () {
        $this->app->instance(PassRepository::class, new class implements PassRepository
        {
            public function exists(DishAtThePassId $id): bool
            {
                throw new RuntimeException('Queries must not hydrate aggregates.');
            }

            public function find(DishAtThePassId $id): DishAtThePass
            {
                throw new RuntimeException('Queries must not hydrate aggregates.');
            }

            public function save(DishAtThePass $dish): void
            {
                throw new RuntimeException('Queries must not hydrate aggregates.');
            }
        });

        $this->getJson('/api/front-of-house/to-serve')->assertOk();
    });

    test('the guests are billed the price they ordered at, not the one on the menu when the dish is served', function () {
        $screen = $this->getJson('/api/kitchen/screen')->json();
        $this->postJson("/api/kitchen/tickets/{$screen[0]['ticketId']}/fire", ['course' => 'starter'])->assertOk();
        $this->postJson("/api/kitchen/tickets/{$screen[0]['ticketId']}/lines/{$screen[0]['lines'][0]['lineId']}/ready")->assertOk();
        DB::table(Tables::MENU_ITEMS)->where('id', 'green-salad')->update(['price_cents' => 1500]);

        $this->postJson('/api/front-of-house/to-serve/'.$this->getJson('/api/front-of-house/to-serve')->json()[0]['id'].'/serve')->assertOk();

        $served = collect(app(EventStore::class)->readAllFrom(0))
            ->first(fn ($event) => $event->type->is('front_of_house.dish_served'));

        expect($served->payload['unitPriceCents'])->toBe(1100)
            ->and($served->payload['kind'])->toBe('food');
    });

    describe('the pass and the list to serve', function () {
        function theDishReadyAtThePass(): array
        {
            $screen = test()->getJson('/api/kitchen/screen')->json();
            test()->postJson("/api/kitchen/tickets/{$screen[0]['ticketId']}/fire", ['course' => 'starter'])->assertOk();
            test()->postJson("/api/kitchen/tickets/{$screen[0]['ticketId']}/lines/{$screen[0]['lines'][0]['lineId']}/ready")->assertOk();

            return [$screen[0]['ticketId'], $screen[0]['lines'][0]['lineId']];
        }

        test('a dish can be served before the list has caught up: the decision is the pass\'s, not the list\'s', function () {
            config(['queue.default' => 'database']);
            [$ticket, $line] = theDishReadyAtThePass();
            $this->getJson('/api/front-of-house/to-serve')->assertExactJson([]);

            $this->postJson('/api/front-of-house/to-serve/'.DishAtThePassId::forLine(new TableOrderId($ticket), new LineId($line)).'/serve')->assertOk();

            $this->postJson('/api/front-of-house/to-serve/'.DishAtThePassId::forLine(new TableOrderId($ticket), new LineId($line)).'/serve')
                ->assertUnprocessable()
                ->assertJson(['message' => 'That dish has already left the pass.']);
        });

        test('a dish served leaves the list once the list has caught up, and a late list does not bring it back', function () {
            config(['queue.default' => 'database']);
            theDishReadyAtThePass();
            $this->artisan('queue:work', ['--stop-when-empty' => true, '--memory' => 2048])->assertSuccessful();
            $id = $this->getJson('/api/front-of-house/to-serve')->json()[0]['id'];
            $this->postJson("/api/front-of-house/to-serve/{$id}/serve")->assertOk();

            $this->artisan('queue:work', ['--stop-when-empty' => true, '--memory' => 2048])->assertSuccessful();

            $this->getJson('/api/front-of-house/to-serve')->assertExactJson([]);
        });

        test('putting the same ready dish at the pass again does not make it waiting again', function () {
            theDishReadyAtThePass();
            $id = $this->getJson('/api/front-of-house/to-serve')->json()[0]['id'];
            $this->postJson("/api/front-of-house/to-serve/{$id}/serve")->assertOk();

            app(CheckpointStore::class)->put('foh.pass', 0);
            $this->artisan('front-of-house:consume', ['--stop-when-empty' => true])->assertSuccessful();

            $this->postJson("/api/front-of-house/to-serve/{$id}/serve")->assertUnprocessable();
        });

        test('the list is rebuilt from the log, without the dishes already served', function () {
            theDishReadyAtThePass();
            $id = $this->getJson('/api/front-of-house/to-serve')->json()[0]['id'];
            $this->postJson('/api/front-of-house/tables/table-7/guests', ['guestCount' => 2])->assertOk();
            $this->postJson('/api/front-of-house/tables/table-7/orders', ['lines' => [['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter']]])->assertOk();
            $screen = collect($this->getJson('/api/kitchen/screen')->json())->firstWhere('partyRef', 'table-7');
            $this->postJson("/api/kitchen/tickets/{$screen['ticketId']}/fire", ['course' => 'starter'])->assertOk();
            $this->postJson("/api/kitchen/tickets/{$screen['ticketId']}/lines/{$screen['lines'][0]['lineId']}/ready")->assertOk();
            $this->postJson("/api/front-of-house/to-serve/{$id}/serve")->assertOk();
            $before = $this->getJson('/api/front-of-house/to-serve')->json();

            app(ToServeProjector::class)->rebuild();

            expect($this->getJson('/api/front-of-house/to-serve')->json())->toBe($before)
                ->and(array_column($before, 'tableId'))->toBe(['table-7']);
        });
    });
});
