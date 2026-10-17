<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\FloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\UnitOfWork;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<FreeTable>
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
     *
     * @return array{tableId: string, accepted: true}
     */
    public function handle(Command $command): array
    {
        assert($command instanceof FreeTable);

        $this->unitOfWork->execute(function () use ($command): void {
            $table = $this->tables->get($command->tableId);
            $table->free();
            $this->tables->save($table);
            $this->floorPlan->sync($table);
        });

        return [
            'tableId' => (string) $command->tableId,
            'accepted' => true,
        ];
    }
}
