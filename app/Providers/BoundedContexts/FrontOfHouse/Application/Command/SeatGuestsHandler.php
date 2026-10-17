<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\FloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\UnitOfWork;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<SeatGuests>
 */
final readonly class SeatGuestsHandler implements CommandHandler
{
    public function __construct(
        private TableRepository $tables,
        private FloorPlan $floorPlan,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * The table decides. The floor plan, a second table, moves in the same transaction.
     *
     * @return array{tableId: string, accepted: true}
     */
    public function handle(Command $command): array
    {
        assert($command instanceof SeatGuests);

        $this->unitOfWork->execute(function () use ($command): void {
            $table = $this->tables->get($command->tableId);
            $table->seat($command->guestCount);
            $this->tables->save($table);
            $this->floorPlan->sync($table);
        });

        return [
            'tableId' => $command->tableId,
            'accepted' => true,
        ];
    }
}
