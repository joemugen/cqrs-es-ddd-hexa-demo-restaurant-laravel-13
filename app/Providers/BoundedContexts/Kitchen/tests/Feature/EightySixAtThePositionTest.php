<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Tables;
use App\Providers\Modules\EventStore\Domain\EventStore;
use Illuminate\Support\Facades\DB;

describe('a dish declared 86', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
    });

    describe('is recorded where it sits in the log, so that orders are judged against that moment', function () {
        test('the position of the 86 is recorded with the dish', function () {
            $this->postJson('/api/kitchen/dishes/dish-steak/eighty-six')->assertOk();

            $event = collect(app(EventStore::class)->readAllFrom(0))
                ->first(fn ($event) => $event->type->is('kitchen.dish_eighty_sixed'));

            expect((int) DB::table(Tables::DISHES)->where('id', 'dish-steak')->value('eighty_sixed_position'))
                ->toBe($event->position);
        });
    });

    describe('with a kitchen worker that is behind', function () {
        test('does not stop the kitchen from opening the ticket of an order placed before it', function () {
            config(['consumption.mode' => 'worker']);
            $this->postJson('/api/front-of-house/tables/table-7/guests', ['guestCount' => 1])->assertOk();
            $this->postJson('/api/front-of-house/tables/table-7/orders', [
                'lines' => [['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main']],
            ])->assertOk();
            $this->postJson('/api/kitchen/dishes/dish-steak/eighty-six')->assertOk();

            $this->artisan('kitchen:consume', ['--stop-when-empty' => true])
                ->doesntExpectOutputToContain('failed')
                ->assertSuccessful();

            expect($this->getJson('/api/kitchen/screen')->json())->toHaveCount(1);
        });
    });
});
