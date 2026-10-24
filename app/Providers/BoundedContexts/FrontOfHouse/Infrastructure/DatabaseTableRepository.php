<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseNotFound;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Table;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class DatabaseTableRepository implements TableRepository
{
    public function get(TableId $id): Table
    {
        $row = DB::table(Tables::TABLES)->where('id', (string) $id)->first();

        if ($row === null) {
            throw new FrontOfHouseNotFound("Unknown table {$id}.");
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
        $this->holdTheVisit($table);

        DB::table(Tables::TABLES)->where('id', (string) $table->id())->update([
            'guest_count' => $table->guestCount(),
            'visit_id' => $table->visit() === null ? null : (string) $table->visit(),
            'visits_seated' => $table->visitsSeated(),
        ]);
    }

    /**
     * The database holds that a table has one open visit: the visit of the guests who sit down is written before the table, and
     * a second visit on a table that already has one is refused by the partial unique index. The visit that leaves is closed.
     */
    private function holdTheVisit(Table $table): void
    {
        if ($table->visit() === null) {
            DB::table(Tables::VISITS)->where('table_id', (string) $table->id())->whereNull('closed_at')->update(['closed_at' => now()]);

            return;
        }

        if (! $table->hasStartedAVisit()) {
            return;
        }

        try {
            DB::table(Tables::VISITS)->insert(['id' => (string) $table->visit(), 'table_id' => (string) $table->id()]);
        } catch (UniqueConstraintViolationException) {
            throw new FrontOfHouseException('This table already has guests.');
        }
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
