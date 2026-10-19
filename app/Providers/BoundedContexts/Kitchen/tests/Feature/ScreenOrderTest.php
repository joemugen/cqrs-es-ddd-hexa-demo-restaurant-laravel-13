<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;

/**
 * Two idempotency keys whose order ids sort against the order the orders are placed in.
 *
 * @return array{string, string}
 */
function keysWhoseIdsSortAgainstTheirOrder(): array
{
    for ($i = 0; ; $i++) {
        $first = TableOrderId::derivedFrom(new TableId('table-4'), "first-{$i}");
        $second = TableOrderId::derivedFrom(new TableId('table-4'), "second-{$i}");

        if ((string) $first > (string) $second) {
            return ["first-{$i}", "second-{$i}"];
        }
    }
}

describe('the kitchen screen order', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
    });

    test('lists the tickets in the order they were opened, never in the order of their ids', function () {
        [$firstKey, $secondKey] = keysWhoseIdsSortAgainstTheirOrder();
        $order = fn (string $course) => ['lines' => [['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => $course]]];

        $first = $this->postJson('/api/front-of-house/tables/table-4/orders', $order('starter'), ['Idempotency-Key' => $firstKey])->json('tableOrderId');
        $second = $this->postJson('/api/front-of-house/tables/table-4/orders', $order('starter'), ['Idempotency-Key' => $secondKey])->json('tableOrderId');

        expect($first > $second)->toBeTrue()
            ->and(collect($this->getJson('/api/kitchen/screen')->json())->pluck('ticketId')->all())->toBe([$first, $second]);
    });
});
