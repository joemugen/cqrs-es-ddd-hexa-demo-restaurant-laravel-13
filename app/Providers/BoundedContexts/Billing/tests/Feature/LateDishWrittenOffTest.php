<?php

use App\Providers\BoundedContexts\Billing\Application\Command\PrepareBill;
use App\Providers\BoundedContexts\Billing\Application\Command\RecordServedDish;
use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\DishKind;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use App\Providers\BoundedContexts\Billing\Infrastructure\Bus\BillingCommandBus;
use Illuminate\Support\Facades\DB;

function aServedDish(string $key, string $description): ServedDish
{
    return new ServedDish(new SourceKey($key), $description, 1, 1100, DishKind::FOOD);
}

describe('a dish served while the bill is paid at the same time', function () {
    beforeEach(function () {
        $this->bus = app(BillingCommandBus::class);
        $this->cover = new CoverRef('visit-1');
        $this->bus->dispatch(new RecordServedDish($this->cover, aServedDish('order-1:line-1', 'Green salad'), 1));
        $this->bus->dispatch(new PrepareBill(new BillId('bill-1'), $this->cover));
    });

    test('is placed on the bill by a ledger that does not know yet that it was paid, and the paid bill writes it off: the loss is the rule', function () {
        config(['consumption.mode' => 'worker']);
        $bill = $this->getJson('/api/billing/covers/visit-1/bill')->assertOk()->json();
        $this->postJson("/api/billing/bills/{$bill['billId']}/payments", ['amountCents' => $bill['balanceCents']], ['If-Match' => '"'.$bill['version'].'"'])->assertOk();

        $this->bus->dispatch(new RecordServedDish($this->cover, aServedDish('order-2:line-1', 'Creme brulee'), 2));
        expect(DB::table('billing_served_items')->where('source_key', 'order-2:line-1')->value('bill_id'))->toBe('bill-1');

        $this->artisan('billing:consume', ['--stop-when-empty' => true])->doesntExpectOutputToContain('failed')->assertSuccessful();

        $this->getJson('/api/billing/covers/visit-1/bill')->assertOk()->assertJsonCount(1, 'lines');
        expect(DB::table('events')->where('type', 'billing.dish_written_off')->count())->toBe(1)
            ->and(DB::table('billing_served_items')->where('source_key', 'order-2:line-1')->value('bill_id'))->toBe('bill-1')
            ->and(DB::table('billing_served_items')->where('source_key', 'order-2:line-1')->value('written_off_at'))->toBeNull();
    });

    test('is written off at once by a ledger that heard of the payment: no bill is bothered', function () {
        $bill = $this->getJson('/api/billing/covers/visit-1/bill')->assertOk()->json();
        $this->postJson("/api/billing/bills/{$bill['billId']}/payments", ['amountCents' => $bill['balanceCents']], ['If-Match' => '"'.$bill['version'].'"'])->assertOk();

        $this->bus->dispatch(new RecordServedDish($this->cover, aServedDish('order-2:line-1', 'Creme brulee'), 2));

        expect(DB::table('billing_served_items')->where('source_key', 'order-2:line-1')->value('written_off_at'))->not->toBeNull()
            ->and(DB::table('events')->where('type', 'billing.dish_written_off')->count())->toBe(1);
    });
});
