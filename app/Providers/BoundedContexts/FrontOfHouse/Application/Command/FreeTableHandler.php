<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\FreeTableAccepted;
use App\Providers\BoundedContexts\FrontOfHouse\Application\FloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<FreeTable, FreeTableAccepted>
 */
#[AsCommandHandler(FreeTable::class)]
final readonly class FreeTableHandler implements CommandHandler
{
    public function __construct(
        private TableRepository $tables,
        private FloorPlan $floorPlan,
        private ContractPublisher $contracts,
    ) {}

    /**
     * The table and the floor plan move together, as they did when the guests sat down. The visit is over, and the fact says so.
     */
    public function __invoke(FreeTable $command): FreeTableAccepted
    {
        $table = $this->tables->hosting($command->visitId);

        if ($table !== null) {
            $table->free($command->visitId);
            $this->tables->save($table);
            $this->floorPlan->sync($table);
            $this->contracts->publish($table->releaseEvents());
        }

        return new FreeTableAccepted($command->visitId);
    }
}
