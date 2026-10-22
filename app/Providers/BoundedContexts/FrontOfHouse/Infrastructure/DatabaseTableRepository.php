<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Table;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use Illuminate\Support\Facades\DB;

final class DatabaseTableRepository implements TableRepository
{
    public function get(TableId $id): Table
    {
        $row = DB::table(Tables::TABLES)->where('id', (string) $id)->first();

        if ($row === null) {
            throw new FrontOfHouseException("Unknown table {$id}.", 404);
        }

        return $this->table($row);
    }

    public function hosting(VisitId $visit): ?Table
    {
        $row = DB::table(Tables::TABLES)->where('visit_id', (string) $visit)->first();

        return $row === null ? null : $this->table($row);
    }

    public function save(Table $table): void
    {
        DB::table(Tables::TABLES)->where('id', (string) $table->id())->update([
            'guest_count' => $table->guestCount(),
            'visit_id' => $table->visit() === null ? null : (string) $table->visit(),
            'visits_seated' => $table->visitsSeated(),
        ]);
    }

    private function table(object $row): Table
    {
        return Table::restore(
            new TableId((string) $row->id),
            new TableLabel((string) $row->label),
            (int) $row->guest_count,
            $row->visit_id !== null ? new VisitId((string) $row->visit_id) : null,
            (int) $row->visits_seated,
        );
    }
}
