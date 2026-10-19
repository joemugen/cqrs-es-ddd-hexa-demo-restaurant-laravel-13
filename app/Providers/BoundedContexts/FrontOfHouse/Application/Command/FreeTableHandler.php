<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\TableFreed;
use App\Providers\BoundedContexts\FrontOfHouse\Application\FloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<FreeTable, TableFreed>
 */
#[AsCommandHandler(FreeTable::class)]
final readonly class FreeTableHandler implements CommandHandler
{
    public function __construct(
        private TableRepository $tables,
        private FloorPlan $floorPlan,
    ) {}

    /**
     * The table and the floor plan move together, as they did when the guests sat down.
     */
    public function __invoke(FreeTable $command): TableFreed
    {
        $table = $this->tables->get($command->tableId);
        $table->free();
        $this->tables->save($table);
        $this->floorPlan->sync($table);

        return new TableFreed($command->tableId);
    }
}
