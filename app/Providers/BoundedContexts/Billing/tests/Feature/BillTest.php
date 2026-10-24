<?php

use App\Providers\BoundedContexts\Billing\Application\BillRepository;
use App\Providers\BoundedContexts\Billing\Application\Command\RecordServedDish;
use App\Providers\BoundedContexts\Billing\Domain\Bill;
use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\DishKind;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use App\Providers\BoundedContexts\Billing\Infrastructure\Bus\BillingCommandBus;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Application\Concurrency\ConcurrencyConflict;
use App\Providers\Modules\SharedKernel\Application\Concurrency\PreconditionFailed;
use Illuminate\Support\Facades\DB;

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
        $this->visit = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk()->json('visitId');

        $this->postJson('/api/front-of-house/tables/table-4/bill')
            ->assertExactJson(['tableId' => 'table-4', 'accepted' => true]);

        $this->getJson("/api/billing/covers/{$this->visit}/bill")->assertNotFound();
    });

    test('the till bills what was served and ignores what is still in the kitchen', function () {
        $this->visit = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk()->json('visitId');
        $this->postJson('/api/front-of-house/tables/table-4/orders', [
            'lines' => [
                ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
                ['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main'],
            ],
        ])->assertOk();

        serveOnTheLatestTicket('starter', 'Green salad');

        $this->postJson('/api/front-of-house/tables/table-4/bill')
            ->assertExactJson(['tableId' => 'table-4', 'accepted' => true]);

        $bill = $this->getJson("/api/billing/covers/{$this->visit}/bill")->assertOk()->json();

        expect($bill['coverRef'])->toBe($this->visit)
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

    test('asking for the bill freezes nothing: a dish served after the request goes on the open bill, which moves to version 2', function () {
        $this->visit = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk()->json('visitId');
        $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter']]])->assertOk();
        serveOnTheLatestTicket('starter', 'Green salad');
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();
        $before = $this->getJson("/api/billing/covers/{$this->visit}/bill")->assertOk()->json();

        $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [['menuItemId' => 'creme-brulee', 'quantity' => 1, 'course' => 'dessert']]])->assertOk();
        serveOnTheLatestTicket('dessert', 'Creme brulee');
        $after = $this->getJson("/api/billing/covers/{$this->visit}/bill")->assertOk()->json();

        expect($before['version'])->toBe(1)
            ->and($after['billId'])->toBe($before['billId'])
            ->and($after['version'])->toBe(2)
            ->and($after['status'])->toBe('open')
            ->and(array_column($after['lines'], 'description'))->toBe(['Green salad', 'Creme brulee'])
            ->and($after['totalCents'])->toBe(2200)
            ->and($after['balanceCents'])->toBe(2200);
    });

    describe('paying a bill', function () {
        beforeEach(function () {
            $this->visit = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk()->json('visitId');
            $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter']]])->assertOk();
            serveOnTheLatestTicket('starter', 'Green salad');
            $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();
        });

        test('shows the version of the note as an ETag, to be sent back with the payment', function () {
            $this->getJson("/api/billing/covers/{$this->visit}/bill")->assertOk()->assertHeader('ETag', '"1"');
        });

        test('is of the version the guests saw: when a dish was added since, the payment is refused and the note is presented again', function () {
            $seen = $this->getJson("/api/billing/covers/{$this->visit}/bill")->json();
            $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [['menuItemId' => 'creme-brulee', 'quantity' => 1, 'course' => 'dessert']]])->assertOk();
            serveOnTheLatestTicket('dessert', 'Creme brulee');

            $this->postJson("/api/billing/bills/{$seen['billId']}/payments", ['amountCents' => $seen['balanceCents']], ['If-Match' => '"1"'])
                ->assertStatus(412)
                ->assertJson(['message' => 'The bill changed since the guests saw it: it is at version 2, not 1. Present it again.']);

            $presented = $this->getJson("/api/billing/covers/{$this->visit}/bill")->assertOk()->assertHeader('ETag', '"2"')->json();
            $this->postJson("/api/billing/bills/{$seen['billId']}/payments", ['amountCents' => $presented['balanceCents']], ['If-Match' => '"2"'])->assertOk();

            expect($presented['totalCents'])->toBe(2200)
                ->and($this->getJson("/api/billing/covers/{$this->visit}/bill")->json('status'))->toBe('paid');
        });

        test('does not take If-Match: * for a version: a wildcard is not a note that was shown', function () {
            $bill = $this->getJson("/api/billing/covers/{$this->visit}/bill")->json();

            $this->postJson("/api/billing/bills/{$bill['billId']}/payments", ['amountCents' => 1100], ['If-Match' => '*'])->assertStatus(428);
        });

        test('names a version: nobody pays a note that no one has shown him', function () {
            $bill = $this->getJson("/api/billing/covers/{$this->visit}/bill")->json();

            $this->postJson("/api/billing/bills/{$bill['billId']}/payments", ['amountCents' => 1100])
                ->assertStatus(428)
                ->assertJson(['message' => 'Send the version of the bill you read as If-Match: the payment is of that note.']);

            expect($this->getJson("/api/billing/covers/{$this->visit}/bill")->json('balanceCents'))->toBe(1100);
        });
    });

    test('a split payment keeps the table until the balance is gone, then the bill is immutable', function () {
        $this->visit = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk()->json('visitId');
        $this->postJson('/api/front-of-house/tables/table-4/orders', [
            'lines' => [
                ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
            ],
        ])->assertOk();
        serveOnTheLatestTicket('starter', 'Green salad');
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();

        $billId = $this->getJson("/api/billing/covers/{$this->visit}/bill")->json()['billId'];

        $this->postJson("/api/billing/bills/{$billId}/payments", [
            'amountCents' => 600,
            'tipCents' => 0,
        ], ['If-Match' => '"1"'])->assertExactJson(['billId' => $billId, 'accepted' => true]);

        $open = $this->getJson("/api/billing/covers/{$this->visit}/bill")->json();
        $table = collect($this->getJson('/api/front-of-house/floor-plan')->json())->firstWhere('tableId', 'table-4');

        expect($open['balanceCents'])->toBe(500)
            ->and($open['status'])->toBe('open')
            ->and($open['tipCents'])->toBe(0)
            ->and($table['status'])->toBe('occupied');

        $this->postJson("/api/billing/bills/{$billId}/payments", [
            'amountCents' => 500,
            'tipCents' => 200,
        ], ['If-Match' => '"1"'])->assertOk();

        $paid = $this->getJson("/api/billing/covers/{$this->visit}/bill")->json();
        $freed = collect($this->getJson('/api/front-of-house/floor-plan')->json())->firstWhere('tableId', 'table-4');

        expect($paid['status'])->toBe('paid')
            ->and($paid['balanceCents'])->toBe(0)
            ->and($paid['tipCents'])->toBe(200)
            ->and($freed['status'])->toBe('free')
            ->and($freed['guestCount'])->toBe(0);

        $this->postJson("/api/billing/bills/{$billId}/payments", [
            'amountCents' => 1,
        ], ['If-Match' => '"1"'])->assertUnprocessable()
            ->assertJson(['message' => 'A paid bill cannot be changed.']);
    });

    test('asking twice does not open a second bill', function () {
        $this->visit = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk()->json('visitId');
        $this->postJson('/api/front-of-house/tables/table-4/orders', [
            'lines' => [
                ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
            ],
        ])->assertOk();
        serveOnTheLatestTicket('starter', 'Green salad');

        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();
        $first = $this->getJson("/api/billing/covers/{$this->visit}/bill")->json()['billId'];
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();
        $second = $this->getJson("/api/billing/covers/{$this->visit}/bill")->json()['billId'];

        expect($second)->toBe($first);
    });

    test('a dish that comes out after the bill was paid is written off, said as a fact and not left on no bill', function () {
        $this->visit = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk()->json('visitId');
        $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter']]])->assertOk();
        serveOnTheLatestTicket('starter', 'Green salad');
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();
        $bill = $this->getJson("/api/billing/covers/{$this->visit}/bill")->json();
        $this->postJson("/api/billing/bills/{$bill['billId']}/payments", ['amountCents' => $bill['balanceCents']], ['If-Match' => '"'.$bill['version'].'"'])->assertOk();

        $late = new RecordServedDish(new CoverRef($this->visit), new ServedDish(new SourceKey('order-2:line-1'), 'Creme brulee', 2, 1100, DishKind::FOOD), 99);
        app(BillingCommandBus::class)->dispatch($late);
        app(BillingCommandBus::class)->dispatch($late);

        $written = collect(app(EventStore::class)->readAllFrom(0))->filter(fn ($event) => $event->type->is('billing.dish_written_off'));

        expect($written)->toHaveCount(1)
            ->and($written->first()->payload)->toMatchArray(['coverRef' => $this->visit, 'billId' => $bill['billId'], 'description' => 'Creme brulee', 'amountCents' => 2200])
            ->and($this->getJson("/api/billing/covers/{$this->visit}/bill")->json())->toMatchArray(['status' => 'paid', 'totalCents' => 1100, 'version' => 1])
            ->and(DB::table('billing_served_items')->whereNull('bill_id')->whereNull('written_off_at')->count())->toBe(0)
            ->and(DB::table('billing_served_items')->whereNotNull('written_off_at')->pluck('source_key')->all())->toBe(['order-2:line-1']);
    });

    test('the bill query does not load the bill aggregate', function () {
        $this->visit = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk()->json('visitId');
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

            public function latestFor(CoverRef $coverRef): ?Bill
            {
                throw new RuntimeException('Queries must not hydrate aggregates.');
            }
        });

        $this->getJson("/api/billing/covers/{$this->visit}/bill")->assertOk();
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

describe('two bills prepared for the same cover', function () {
    test('cannot both be open: the database holds one open bill per cover, so the second is refused', function () {
        $dish = new ServedDish(new SourceKey('order-1:line-1'), 'Green salad', 1, 1100, DishKind::FOOD);
        $bills = app(BillRepository::class);
        $bills->save(Bill::prepare(new BillId('first'), new CoverRef('visit-1'), [$dish]));

        expect(fn () => $bills->save(Bill::prepare(new BillId('second'), new CoverRef('visit-1'), [$dish])))->toThrow(ConcurrencyConflict::class)
            ->and(DB::table('billing_bills')->where('cover_ref', 'visit-1')->count())->toBe(1);
    });

    test('are allowed once the first one is paid, for the cover keeps its history', function () {
        $dish = new ServedDish(new SourceKey('order-1:line-1'), 'Green salad', 1, 1100, DishKind::FOOD);
        $bills = app(BillRepository::class);
        $first = Bill::prepare(new BillId('first'), new CoverRef('visit-1'), [$dish]);
        $first->pay(1100, 0);
        $bills->save($first);

        $bills->save(Bill::prepare(new BillId('second'), new CoverRef('visit-1'), [$dish]));

        expect(DB::table('billing_bills')->where('cover_ref', 'visit-1')->count())->toBe(2);
    });
});

describe('two payments that read the same bill', function () {
    test('cannot both be saved, whatever the database: the second finds the bill changed, with a 412', function () {
        $dish = new ServedDish(new SourceKey('order-1:line-1'), 'Green salad', 1, 1100, DishKind::FOOD);
        $bills = app(BillRepository::class);
        $bills->save(Bill::prepare(new BillId('bill-1'), new CoverRef('visit-1'), [$dish]));
        $first = $bills->get(new BillId('bill-1'));
        $second = $bills->get(new BillId('bill-1'));
        $first->pay(600, 0);
        $second->pay(600, 0);
        $bills->save($first);

        expect(fn () => $bills->save($second))->toThrow(PreconditionFailed::class)
            ->and($bills->get(new BillId('bill-1'))->balanceCents())->toBe(500);
    });

    test('are still allowed one after the other on the same version of the note: a payment does not change the note', function () {
        $dish = new ServedDish(new SourceKey('order-1:line-1'), 'Green salad', 1, 1100, DishKind::FOOD);
        $bills = app(BillRepository::class);
        $bills->save(Bill::prepare(new BillId('bill-1'), new CoverRef('visit-1'), [$dish]));

        foreach ([600, 500] as $amount) {
            $bill = $bills->get(new BillId('bill-1'));
            $bill->pay($amount, 0);
            $bills->save($bill);
        }

        expect($bills->get(new BillId('bill-1'))->isPaid())->toBeTrue();
    });
});

describe('the bill of a cover', function () {
    test('is the latest one, whatever its id sorts like', function () {
        $dish = new ServedDish(new SourceKey('ticket-1:line-1'), 'Green salad', 1, 1100, DishKind::FOOD);
        $bills = app(BillRepository::class);

        $first = Bill::prepare(new BillId('zzzz-opened-first'), new CoverRef('visit-1'), [$dish]);
        $first->pay(1100, 0);
        $bills->save($first);
        $bills->save(Bill::prepare(new BillId('aaaa-opened-second'), new CoverRef('visit-1'), [$dish]));

        $this->getJson('/api/billing/covers/visit-1/bill')
            ->assertOk()
            ->assertJsonPath('billId', 'aaaa-opened-second');
    });
});
