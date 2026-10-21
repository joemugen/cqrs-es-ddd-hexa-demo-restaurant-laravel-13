<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Application\DishCatalog;
use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use App\Providers\BoundedContexts\Kitchen\Domain\OrderedLine;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Tables;
use App\Providers\Modules\EventStore\Domain\EventStore;
use Illuminate\Support\Facades\DB;

function steakOrderedAs(string $line): OrderedLine
{
    return new OrderedLine(new LineId($line), new DishName('Steak frites'), 1, 'main', ['gluten']);
}

describe('a dish declared 86', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
    });

    describe('is judged where the order sits in the log', function () {
        test('the position of the 86 is recorded with the dish', function () {
            $this->postJson('/api/kitchen/dishes/dish-steak/eighty-six')->assertOk();

            $event = collect(app(EventStore::class)->readAllFrom(0))
                ->first(fn ($event) => $event->type->is('kitchen.dish_eighty_sixed'));

            expect((int) DB::table(Tables::DISHES)->where('id', 'dish-steak')->value('eighty_sixed_position'))
                ->toBe($event->position);
        });

        test('an order placed before the 86 still gets its dish', function () {
            DB::table(Tables::DISHES)->where('id', 'dish-steak')->update(['in_stock' => false, 'eighty_sixed_position' => 10]);

            $lines = app(DishCatalog::class)->linesFor([steakOrderedAs('order-1')], 9);

            expect($lines->unavailable)->toBe([])
                ->and($lines->available)->toHaveCount(1)
                ->and((string) $lines->available[0]->dishName)->toBe('Steak frites');
        });

        test('an order placed after the 86 gets it as unavailable, with the reason', function () {
            DB::table(Tables::DISHES)->where('id', 'dish-steak')->update(['in_stock' => false, 'eighty_sixed_position' => 10]);

            $lines = app(DishCatalog::class)->linesFor([steakOrderedAs('order-1')], 11);

            expect($lines->available)->toBe([])
                ->and($lines->unavailable)->toHaveCount(1)
                ->and((string) $lines->unavailable[0]->lineId)->toBe('order-1')
                ->and((string) $lines->unavailable[0]->dishName)->toBe('Steak frites')
                ->and($lines->unavailable[0]->reason)->toBe('Steak frites is 86.');
        });

        test('a dish out of stock with no recorded position stays unavailable', function () {
            DB::table(Tables::DISHES)->where('id', 'dish-steak')->update(['in_stock' => false]);

            expect(app(DishCatalog::class)->linesFor([steakOrderedAs('order-1')], 1)->unavailable)->toHaveCount(1);
        });

        test('a dish the kitchen does not know is unavailable too, and does not hide the others', function () {
            $unknown = new OrderedLine(new LineId('order-2'), new DishName('Dragon steak'), 1, 'main', []);
            $lines = app(DishCatalog::class)->linesFor([steakOrderedAs('order-1'), $unknown], 1);

            expect($lines->available)->toHaveCount(1)
                ->and($lines->unavailable[0]->reason)->toBe('The kitchen has no dish called Dragon steak.');
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
