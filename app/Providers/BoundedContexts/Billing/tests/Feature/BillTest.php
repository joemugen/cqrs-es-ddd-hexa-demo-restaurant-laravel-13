<?php

use App\Providers\BoundedContexts\Billing\Application\BillRepository;
use App\Providers\BoundedContexts\Billing\Domain\Bill;
use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;

describe('the till', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
    });

    test('an empty table cannot request a bill', function () {
        $this->postJson('/api/front-of-house/tables/table-4/bill')
            ->assertUnprocessable()
            ->assertJson(['message' => 'This table has no guests to bill.']);
    });

    test('asking for a bill before anything is served does not open one', function () {
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();

        $this->postJson('/api/front-of-house/tables/table-4/bill')
            ->assertExactJson(['tableId' => 'table-4', 'accepted' => true]);

        $this->getJson('/api/billing/covers/table-4/bill')->assertNotFound();
    });

    test('the till bills what was served and ignores what is still in the kitchen', function () {
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->postJson('/api/front-of-house/tables/table-4/orders', [
            'lines' => [
                ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
                ['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main'],
            ],
        ])->assertOk();

        serveOnTheLatestTicket('starter', 'Green salad');

        $this->postJson('/api/front-of-house/tables/table-4/bill')
            ->assertExactJson(['tableId' => 'table-4', 'accepted' => true]);

        $bill = $this->getJson('/api/billing/covers/table-4/bill')->assertOk()->json();

        expect($bill['coverRef'])->toBe('table-4')
            ->and($bill['status'])->toBe('open')
            ->and($bill['lines'])->toHaveCount(1)
            ->and($bill['lines'][0])->toMatchArray([
                'description' => 'Green salad',
                'quantity' => 1,
                'unitPriceCents' => 1100,
                'kind' => 'food',
                'vatCents' => 100,
            ])
            ->and($bill['totalCents'])->toBe(1100)
            ->and($bill['vatCents'])->toBe(100)
            ->and($bill['balanceCents'])->toBe(1100)
            ->and($bill['foodGrossCents'])->toBe(1100)
            ->and($bill['alcoholGrossCents'])->toBe(0);
    });

    test('a split payment keeps the table until the balance is gone, then the bill is immutable', function () {
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->postJson('/api/front-of-house/tables/table-4/orders', [
            'lines' => [
                ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
            ],
        ])->assertOk();
        serveOnTheLatestTicket('starter', 'Green salad');
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();

        $billId = $this->getJson('/api/billing/covers/table-4/bill')->json()['billId'];

        $this->postJson("/api/billing/bills/{$billId}/payments", [
            'amountCents' => 600,
            'tipCents' => 0,
        ])->assertExactJson(['billId' => $billId, 'accepted' => true]);

        $open = $this->getJson('/api/billing/covers/table-4/bill')->json();
        $table = collect($this->getJson('/api/front-of-house/floor-plan')->json())->firstWhere('tableId', 'table-4');

        expect($open['balanceCents'])->toBe(500)
            ->and($open['status'])->toBe('open')
            ->and($open['tipCents'])->toBe(0)
            ->and($table['status'])->toBe('occupied');

        $this->postJson("/api/billing/bills/{$billId}/payments", [
            'amountCents' => 500,
            'tipCents' => 200,
        ])->assertOk();

        $paid = $this->getJson('/api/billing/covers/table-4/bill')->json();
        $freed = collect($this->getJson('/api/front-of-house/floor-plan')->json())->firstWhere('tableId', 'table-4');

        expect($paid['status'])->toBe('paid')
            ->and($paid['balanceCents'])->toBe(0)
            ->and($paid['tipCents'])->toBe(200)
            ->and($freed['status'])->toBe('free')
            ->and($freed['guestCount'])->toBe(0);

        $this->postJson("/api/billing/bills/{$billId}/payments", [
            'amountCents' => 1,
        ])->assertUnprocessable()
            ->assertJson(['message' => 'A paid bill cannot be changed.']);
    });

    test('asking twice does not open a second bill', function () {
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->postJson('/api/front-of-house/tables/table-4/orders', [
            'lines' => [
                ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
            ],
        ])->assertOk();
        serveOnTheLatestTicket('starter', 'Green salad');

        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();
        $first = $this->getJson('/api/billing/covers/table-4/bill')->json()['billId'];
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();
        $second = $this->getJson('/api/billing/covers/table-4/bill')->json()['billId'];

        expect($second)->toBe($first);
    });

    test('the bill query does not load the bill aggregate', function () {
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->postJson('/api/front-of-house/tables/table-4/orders', [
            'lines' => [
                ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
            ],
        ])->assertOk();
        serveOnTheLatestTicket('starter', 'Green salad');
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();

        $this->app->instance(BillRepository::class, new class implements BillRepository
        {
            public function get(BillId $id): Bill
            {
                throw new RuntimeException('Queries must not hydrate aggregates.');
            }

            public function save(Bill $bill): void
            {
                throw new RuntimeException('Queries must not hydrate aggregates.');
            }

            public function openFor(CoverRef $coverRef): ?Bill
            {
                throw new RuntimeException('Queries must not hydrate aggregates.');
            }
        });

        $this->getJson('/api/billing/covers/table-4/bill')->assertOk();
    });
});

function serveOnTheLatestTicket(string $course, string $dishName): void
{
    $ticket = collect(test()->getJson('/api/kitchen/screen')->json())->last();
    $line = collect($ticket['lines'])->firstWhere('dishName', $dishName);

    test()->postJson("/api/kitchen/tickets/{$ticket['ticketId']}/fire", ['course' => $course])->assertOk();
    test()->postJson("/api/kitchen/tickets/{$ticket['ticketId']}/lines/{$line['lineId']}/ready")->assertOk();

    $waiting = collect(test()->getJson('/api/front-of-house/to-serve')->json())
        ->firstWhere('lineId', $line['lineId']);

    test()->postJson("/api/front-of-house/to-serve/{$waiting['id']}/serve")->assertOk();
}
