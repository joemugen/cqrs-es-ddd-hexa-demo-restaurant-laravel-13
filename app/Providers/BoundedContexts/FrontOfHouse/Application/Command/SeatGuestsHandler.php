<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\TableSeated;
use App\Providers\BoundedContexts\FrontOfHouse\Application\FloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<SeatGuests, TableSeated>
 */
#[AsCommandHandler(SeatGuests::class)]
final readonly class SeatGuestsHandler implements CommandHandler
{
    public function __construct(
        private TableRepository $tables,
        private FloorPlan $floorPlan,
    ) {}

    /**
     * The table decides. The floor plan, a second table, moves in the same transaction.
     */
    public function __invoke(SeatGuests $command): TableSeated
    {
        $table = $this->tables->get($command->tableId);
        $visit = $table->seat($command->guestCount);
        $this->tables->save($table);
        $this->floorPlan->sync($table);

        return new TableSeated($command->tableId, $visit);
    }
}
