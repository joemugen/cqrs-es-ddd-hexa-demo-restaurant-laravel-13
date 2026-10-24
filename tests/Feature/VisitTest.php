<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use Illuminate\Support\Facades\DB;

function cookedFor(string $table, string $item, string $course): string
{
    $id = test()->postJson("/api/front-of-house/tables/{$table}/orders", ['lines' => [['menuItemId' => $item, 'quantity' => 1, 'course' => $course]]])->json('tableOrderId');
    test()->postJson("/api/kitchen/tickets/{$id}/fire", ['course' => $course])->assertOk();
    $ticket = collect(test()->getJson('/api/kitchen/screen')->json())->firstWhere('ticketId', $id);
    test()->postJson("/api/kitchen/tickets/{$id}/lines/{$ticket['lines'][0]['lineId']}/ready")->assertOk();

    return $id;
}

function everythingAtThePassIsServed(): void
{
    foreach (test()->getJson('/api/front-of-house/to-serve')->json() as $dish) {
        test()->postJson("/api/front-of-house/to-serve/{$dish['id']}/serve")->assertOk();
    }
}

function theLinesOfTheBillOf(string $visit): array
{
    return array_column(test()->getJson("/api/billing/covers/{$visit}/bill")->assertOk()->json('lines'), 'description');
}

function catchUp(string ...$contexts): void
{
    foreach ($contexts as $context) {
        test()->artisan("{$context}:consume", ['--stop-when-empty' => true])->doesntExpectOutputToContain('failed')->assertSuccessful();
    }
}

describe('a visit of a table', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
        $this->visit = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk()->json('visitId');
    });

    test('is told to the waiter when the guests sit down, and is not the table', function () {
        expect($this->visit)->toBeString()->not->toBe('table-4');
    });

    test('a dessert served after the bill was prepared goes on the bill of its own visit, and not on the bill of the next guests', function () {
        cookedFor('table-4', 'green-salad', 'starter');
        everythingAtThePassIsServed();
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();

        cookedFor('table-4', 'creme-brulee', 'dessert');
        everythingAtThePassIsServed();
        $first = $this->getJson("/api/billing/covers/{$this->visit}/bill")->json();
        $this->postJson("/api/billing/bills/{$first['billId']}/payments", ['amountCents' => $first['balanceCents']], ['If-Match' => '"'.$first['version'].'"'])->assertOk();

        $next = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk()->json('visitId');
        cookedFor('table-4', 'house-red', 'drink');
        everythingAtThePassIsServed();
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();

        expect($next)->not->toBe($this->visit)
            ->and(theLinesOfTheBillOf($this->visit))->toBe(['Green salad', 'Creme brulee'])
            ->and(theLinesOfTheBillOf($next))->toBe(['House red']);
    });

    test('a dish served after the bill was requested goes on that bill, even when the till prepares it late', function () {
        cookedFor('table-4', 'green-salad', 'starter');
        everythingAtThePassIsServed();

        config(['consumption.mode' => 'worker']);
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();
        $id = $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [['menuItemId' => 'creme-brulee', 'quantity' => 1, 'course' => 'dessert']]])->json('tableOrderId');
        catchUp('front-of-house', 'kitchen', 'front-of-house');
        $this->postJson("/api/kitchen/tickets/{$id}/fire", ['course' => 'dessert'])->assertOk();
        $ticket = collect($this->getJson('/api/kitchen/screen')->json())->firstWhere('ticketId', $id);
        $this->postJson("/api/kitchen/tickets/{$id}/lines/{$ticket['lines'][0]['lineId']}/ready")->assertOk();
        catchUp('front-of-house');
        everythingAtThePassIsServed();

        catchUp('billing');

        expect(theLinesOfTheBillOf($this->visit))->toBe(['Green salad', 'Creme brulee']);
    });

    test('the payment of the previous guests does not free the table of the next ones, even when the dining room hears it again', function () {
        cookedFor('table-4', 'green-salad', 'starter');
        everythingAtThePassIsServed();
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();
        $bill = $this->getJson("/api/billing/covers/{$this->visit}/bill")->json();
        $this->postJson("/api/billing/bills/{$bill['billId']}/payments", ['amountCents' => $bill['balanceCents']], ['If-Match' => '"'.$bill['version'].'"'])->assertOk();
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 3])->assertOk();

        config(['consumption.mode' => 'worker']);
        app(CheckpointStore::class)->put('foh.free-table', 0);
        catchUp('front-of-house');

        $table = collect($this->getJson('/api/front-of-house/floor-plan')->json())->firstWhere('tableId', 'table-4');

        expect($table)->toMatchArray(['status' => 'occupied', 'guestCount' => 3]);
    });

    test('a revision for guests who left is obsolete, even when other guests sit at the table', function () {
        config(['consumption.mode' => 'worker']);
        $this->postJson('/api/kitchen/dishes/dish-steak/eighty-six')->assertOk();
        $id = $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main']]])->json('tableOrderId');
        catchUp('front-of-house', 'kitchen', 'front-of-house');

        config(['consumption.mode' => 'sync']);
        cookedFor('table-4', 'green-salad', 'starter');
        everythingAtThePassIsServed();
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();
        $bill = $this->getJson("/api/billing/covers/{$this->visit}/bill")->json();
        $this->postJson("/api/billing/bills/{$bill['billId']}/payments", ['amountCents' => $bill['balanceCents']], ['If-Match' => '"'.$bill['version'].'"'])->assertOk();
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();

        $this->postJson("/api/front-of-house/orders/{$id}/revisions", ['lines' => [['menuItemId' => 'creme-brulee', 'quantity' => 1, 'course' => 'dessert']]])
            ->assertStatus(412)
            ->assertJson(['message' => 'The revision of the order is obsolete: the guests already paid and left.']);
    });
});

function theServedDishesOnNoBill(): array
{
    return DB::table('billing_served_items')->whereNull('bill_id')->whereNull('written_off_at')->pluck('description')->all();
}

function theGuestsPayTheirBill(string $visit): void
{
    $bill = test()->getJson("/api/billing/covers/{$visit}/bill")->assertOk()->json();
    test()->postJson("/api/billing/bills/{$bill['billId']}/payments", ['amountCents' => $bill['balanceCents']], ['If-Match' => '"'.$bill['version'].'"'])->assertOk();
}

describe('a dish served late', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
        $this->visit = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk()->json('visitId');
    });

    test('is on a bill of its visit, or was not served: once the guests have paid, no dish served to them is owed by nobody', function () {
        cookedFor('table-4', 'green-salad', 'starter');
        everythingAtThePassIsServed();
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();
        cookedFor('table-4', 'creme-brulee', 'dessert');
        everythingAtThePassIsServed();

        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();
        theGuestsPayTheirBill($this->visit);

        expect(theServedDishesOnNoBill())->toBe([]);
    });

    test('is on a bill of its visit, or is not served, when the guests had already paid and left', function () {
        cookedFor('table-4', 'creme-brulee', 'dessert');
        cookedFor('table-4', 'green-salad', 'starter');
        $waiting = collect($this->getJson('/api/front-of-house/to-serve')->json());
        $this->postJson('/api/front-of-house/to-serve/'.$waiting->firstWhere('dishName', 'Green salad')['id'].'/serve')->assertOk();
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();
        theGuestsPayTheirBill($this->visit);

        $this->postJson('/api/front-of-house/to-serve/'.$waiting->firstWhere('dishName', 'Creme brulee')['id'].'/serve')->assertStatus(409);

        expect(theServedDishesOnNoBill())->toBe([]);
    });

    test('is refused when the guests paid and left: nothing is served to a closed visit', function () {
        cookedFor('table-4', 'green-salad', 'starter');
        $ready = $this->getJson('/api/front-of-house/to-serve')->json()[0]['id'];
        $this->postJson("/api/front-of-house/to-serve/{$ready}/serve")->assertOk();
        cookedFor('table-4', 'creme-brulee', 'dessert');
        $dessert = collect($this->getJson('/api/front-of-house/to-serve')->json())->firstWhere('dishName', 'Creme brulee')['id'];
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();
        theGuestsPayTheirBill($this->visit);

        $this->postJson("/api/front-of-house/to-serve/{$dessert}/serve")
            ->assertStatus(409)
            ->assertJson(['message' => 'The guests of this visit already paid and left: nothing is served to them.']);
    });

    test('closes the orders of the visit once it is paid: the kitchen stops what it still cooks and the pass keeps nothing for them', function () {
        cookedFor('table-4', 'green-salad', 'starter');
        everythingAtThePassIsServed();
        cookedFor('table-4', 'steak-frites', 'main');
        $dessert = $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [['menuItemId' => 'creme-brulee', 'quantity' => 1, 'course' => 'dessert']]])->json('tableOrderId');
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();
        expect(collect($this->getJson('/api/kitchen/screen')->json())->pluck('ticketId')->all())->toContain($dessert);

        theGuestsPayTheirBill($this->visit);

        expect(collect($this->getJson('/api/kitchen/screen')->json())->pluck('ticketId')->all())->not->toContain($dessert)
            ->and($this->getJson('/api/front-of-house/to-serve')->json())->toBe([]);
    });
});
