<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Queries;

use Illuminate\Support\Facades\DB;

final class MenuQuery
{
    /**
     * The menu the guest reads. It is not the kitchen ticket.
     *
     * @return list<array{menuItemId: string, name: string, description: string, priceCents: int, allergens: list<string>, available: bool, kind: string}>
     */
    public function all(): array
    {
        return DB::table('foh_menu_items')
            ->orderBy('name')
            ->get()
            ->map(function (object $row): array {
                $allergens = $row->allergens;
                if (is_string($allergens)) {
                    $allergens = json_decode($allergens, true, 512, JSON_THROW_ON_ERROR);
                }

                return [
                    'menuItemId' => (string) $row->id,
                    'name' => (string) $row->name,
                    'description' => (string) $row->description,
                    'priceCents' => (int) $row->price_cents,
                    'allergens' => is_array($allergens) ? array_values($allergens) : [],
                    'available' => (bool) $row->available,
                    'kind' => (string) $row->kind,
                ];
            })
            ->all();
    }
}
