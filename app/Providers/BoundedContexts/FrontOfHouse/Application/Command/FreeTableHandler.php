<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\TableFreed;
use App\Providers\BoundedContexts\FrontOfHouse\Application\FloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\UnitOfWork;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<FreeTable, TableFreed>
 */
final readonly class FreeTableHandler implements CommandHandler
{
    public function __construct(
        private TableRepository $tables,
        private FloorPlan $floorPlan,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * The table and the floor plan move together, as they did when the guests sat down.
     */
    public function handle(Command $command): TableFreed
    {
        assert($command instanceof FreeTable);

        $this->unitOfWork->execute(function () use ($command): void {
            $table = $this->tables->get($command->tableId);
            $table->free();
            $this->tables->save($table);
            $this->floorPlan->sync($table);
        });

        return new TableFreed($command->tableId);
    }
}
