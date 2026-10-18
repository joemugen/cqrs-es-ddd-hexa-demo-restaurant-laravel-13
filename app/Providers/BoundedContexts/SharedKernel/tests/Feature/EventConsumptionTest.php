<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\ContextDiscovery;
use Illuminate\Support\Facades\Artisan;

describe('event consumption', function (): void {
    describe('discovery', function (): void {
        it('finds the consumers of a context', function (string $context): void {
            $found = app(ContextDiscovery::class)->eventConsumers(app_path('Providers/BoundedContexts/'.$context));

            expect($found)->toEqualCanonicalizing(
                array_values(array_filter(
                    [...classesIn($context.'/Infrastructure/Processors/*.php'), ...classesIn($context.'/Infrastructure/Projection/*Projector.php')],
                )),
            );
        })->with(['FrontOfHouse', 'Kitchen', 'Billing']);

        it('has a consume command per context', function (string $command): void {
            expect(array_key_exists($command, Artisan::all()))->toBeTrue("{$command} is not registered");
        })->with(['front-of-house:consume', 'kitchen:consume', 'billing:consume']);
    });

    describe('in worker mode', function (): void {
        beforeEach(function (): void {
            config(['consumption.mode' => 'worker']);
            $this->seed(FrontOfHouseSeeder::class);
            $this->seed(KitchenSeeder::class);
            $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
            $this->postJson('/api/front-of-house/tables/table-4/orders', [
                'lines' => [['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter']],
            ])->assertOk();
        });

        it('leaves the kitchen deaf until its worker runs', function (): void {
            $this->getJson('/api/kitchen/screen')->assertExactJson([]);

            $this->artisan('kitchen:consume', ['--stop-when-empty' => true])->assertSuccessful();

            expect($this->getJson('/api/kitchen/screen')->json())->toHaveCount(1);
        });

        it('lets the dining room hear the kitchen only through its own worker', function (): void {
            $this->artisan('kitchen:consume', ['--stop-when-empty' => true])->assertSuccessful();
            $ticketId = $this->getJson('/api/kitchen/screen')->json()[0]['ticketId'];
            $lineId = $this->getJson('/api/kitchen/screen')->json()[0]['lines'][0]['lineId'];
            $this->postJson("/api/kitchen/tickets/{$ticketId}/fire", ['course' => 'starter'])->assertOk();
            $this->postJson("/api/kitchen/tickets/{$ticketId}/lines/{$lineId}/ready")->assertOk();

            $this->getJson('/api/front-of-house/to-serve')->assertExactJson([]);

            $this->artisan('front-of-house:consume', ['--stop-when-empty' => true])->assertSuccessful();

            expect($this->getJson('/api/front-of-house/to-serve')->json())->toHaveCount(1);
        });

        it('reports what it consumed', function (): void {
            $this->artisan('kitchen:consume', ['--stop-when-empty' => true])
                ->expectsOutputToContain('Consumed')
                ->assertSuccessful();
        });
    });

    describe('in sync mode', function (): void {
        it('is the default, so the kitchen hears the order at once', function (): void {
            expect(config('consumption.mode'))->toBe('sync');

            $this->seed(FrontOfHouseSeeder::class);
            $this->seed(KitchenSeeder::class);
            $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
            $this->postJson('/api/front-of-house/tables/table-4/orders', [
                'lines' => [['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter']],
            ])->assertOk();

            expect($this->getJson('/api/kitchen/screen')->json())->toHaveCount(1);
        });
    });
});
