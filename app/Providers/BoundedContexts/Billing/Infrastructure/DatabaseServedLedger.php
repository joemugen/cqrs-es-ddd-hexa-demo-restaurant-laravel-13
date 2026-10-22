<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure;

use App\Providers\BoundedContexts\Billing\Application\ServedLedger;
use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use Illuminate\Support\Facades\DB;

final class DatabaseServedLedger implements ServedLedger
{
    public function record(CoverRef $coverRef, ServedDish $dish, int $position): bool
    {
        return DB::table(Tables::SERVED_ITEMS)->insertOrIgnore([
            'source_key' => (string) $dish->sourceKey,
            'cover_ref' => (string) $coverRef,
            'description' => $dish->description,
            'quantity' => $dish->quantity,
            'unit_price_cents' => $dish->unitPriceCents,
            'kind' => $dish->kind,
            'position' => $position,
        ]) > 0;
    }

    public function writeOff(SourceKey $sourceKey): void
    {
        DB::table(Tables::SERVED_ITEMS)->where('source_key', (string) $sourceKey)->update(['written_off_at' => now()]);
    }

    public function unbilled(CoverRef $coverRef): array
    {
        return DB::table(Tables::SERVED_ITEMS)
            ->where('cover_ref', (string) $coverRef)
            ->whereNull('bill_id')
            ->whereNull('written_off_at')
            ->orderBy('position')
            ->orderBy('source_key')
            ->get()
            ->map(fn (object $row): ServedDish => new ServedDish(
                new SourceKey((string) $row->source_key),
                (string) $row->description,
                (int) $row->quantity,
                (int) $row->unit_price_cents,
                (string) $row->kind,
            ))
            ->all();
    }

    public function attach(BillId $billId, array $sourceKeys): void
    {
        if ($sourceKeys === []) {
            return;
        }

        DB::table(Tables::SERVED_ITEMS)
            ->whereIn('source_key', array_map(fn (SourceKey $key): string => (string) $key, $sourceKeys))
            ->update(['bill_id' => (string) $billId]);
    }
}
