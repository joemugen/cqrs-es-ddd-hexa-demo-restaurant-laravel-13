<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Query;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\ListMenu;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\Response\MenuEntry;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\Response\MenuView;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsQueryHandler;
use App\Providers\Modules\SharedKernel\Application\QueryHandler;
use Illuminate\Support\Facades\DB;

/**
 * @implements QueryHandler<ListMenu, MenuView>
 */
#[AsQueryHandler(ListMenu::class)]
final class ListMenuHandler implements QueryHandler
{
    /**
     * The menu the guest reads. It is not the kitchen ticket.
     */
    public function __invoke(ListMenu $query): MenuView
    {
        return new MenuView(DB::table(Tables::MENU_ITEMS)
            ->orderBy('name')
            ->get()
            ->map(function (object $row): MenuEntry {
                $allergens = $row->allergens;
                if (is_string($allergens)) {
                    $allergens = json_decode($allergens, true, 512, JSON_THROW_ON_ERROR);
                }

                return new MenuEntry(
                    new MenuItemId((string) $row->id),
                    new DishName((string) $row->name),
                    (string) $row->description,
                    (int) $row->price_cents,
                    is_array($allergens) ? array_values($allergens) : [],
                    (bool) $row->available,
                    (string) $row->kind,
                );
            })
            ->all());
    }
}
