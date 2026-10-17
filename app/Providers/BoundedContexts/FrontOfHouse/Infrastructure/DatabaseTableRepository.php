<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Table;
use Illuminate\Support\Facades\DB;

final class DatabaseTableRepository implements TableRepository
{
    public function get(string $id): Table
    {
        $row = DB::table('foh_tables')->where('id', $id)->first();

        if ($row === null) {
            throw new FrontOfHouseException("Unknown table {$id}.", 404);
        }

        return Table::restore((string) $row->id, (string) $row->label, (int) $row->guest_count);
    }

    public function save(Table $table): void
    {
        DB::table('foh_tables')->where('id', $table->id())->update([
            'guest_count' => $table->guestCount(),
        ]);
    }
}
