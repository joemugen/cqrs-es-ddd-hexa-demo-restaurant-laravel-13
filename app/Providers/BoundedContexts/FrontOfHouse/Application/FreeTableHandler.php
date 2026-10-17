<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

final readonly class FreeTableHandler
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
    public function handle(FreeTable $command): array
    {
        $this->unitOfWork->execute(function () use ($command): void {
            $table = $this->tables->get($command->tableId);
            $table->free();
            $this->tables->save($table);
            $this->floorPlan->sync($table);
        });

        return [
            'tableId' => $command->tableId,
            'accepted' => true,
        ];
    }
}
