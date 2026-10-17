<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

use App\Providers\BoundedContexts\FrontOfHouse\Application\PassRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePass;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use Illuminate\Support\Facades\DB;

final class DatabasePassRepository implements PassRepository
{
    public function find(string $id): DishAtThePass
    {
        $row = DB::table('foh_to_serve')->where('id', $id)->first();

        if ($row === null) {
            throw new FrontOfHouseException("Nothing at the pass with id {$id}.", 404);
        }

        return new DishAtThePass(
            (string) $row->id,
            (string) $row->table_id,
            (string) $row->dish_name,
            (int) $row->quantity,
            (string) $row->ticket_id,
            (string) $row->line_id,
            $row->served_at !== null,
        );
    }

    public function save(DishAtThePass $dish): void
    {
        DB::table('foh_to_serve')->where('id', $dish->id())->update([
            'served_at' => $dish->isServed() ? now() : null,
        ]);
    }
}
