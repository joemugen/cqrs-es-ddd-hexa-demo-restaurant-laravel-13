<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure;

use App\Providers\BoundedContexts\Billing\Application\ServedLedger;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use Illuminate\Support\Facades\DB;

final class DatabaseServedLedger implements ServedLedger
{
    public function record(string $coverRef, ServedDish $dish): void
    {
        DB::table('billing_served_items')->insertOrIgnore([
            'source_key' => $dish->sourceKey,
            'cover_ref' => $coverRef,
            'description' => $dish->description,
            'quantity' => $dish->quantity,
            'unit_price_cents' => $dish->unitPriceCents,
            'kind' => $dish->kind,
        ]);
    }

    public function unbilled(string $coverRef): array
    {
        return DB::table('billing_served_items')
            ->where('cover_ref', $coverRef)
            ->whereNull('bill_id')
            ->orderBy('source_key')
            ->get()
            ->map(fn (object $row): ServedDish => new ServedDish(
                (string) $row->source_key,
                (string) $row->description,
                (int) $row->quantity,
                (int) $row->unit_price_cents,
                (string) $row->kind,
            ))
            ->all();
    }

    public function attach(string $billId, array $sourceKeys): void
    {
        if ($sourceKeys === []) {
            return;
        }

        DB::table('billing_served_items')
            ->whereIn('source_key', $sourceKeys)
            ->update(['bill_id' => $billId]);
    }
}
