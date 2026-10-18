<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

use App\Providers\BoundedContexts\FrontOfHouse\Application\PassRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePass;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TicketId;
use Illuminate\Support\Facades\DB;

final class DatabasePassRepository implements PassRepository
{
    public function find(DishAtThePassId $id): DishAtThePass
    {
        $row = DB::table('foh_to_serve')->where('id', (string) $id)->first();

        if ($row === null) {
            throw new FrontOfHouseException('Nothing at the pass with id :id.', 404, ['id' => $id]);
        }

        return new DishAtThePass(
            new DishAtThePassId((string) $row->id),
            new TableId((string) $row->table_id),
            new DishName((string) $row->dish_name),
            (int) $row->quantity,
            new TicketId((string) $row->ticket_id),
            new LineId((string) $row->line_id),
            $row->served_at !== null,
        );
    }

    public function save(DishAtThePass $dish): void
    {
        DB::table('foh_to_serve')->where('id', (string) $dish->id())->update([
            'served_at' => $dish->isServed() ? now() : null,
        ]);
    }
}
