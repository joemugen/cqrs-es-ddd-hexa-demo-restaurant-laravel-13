<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\EventStore\Application\IntegrationBus;
use App\Providers\BoundedContexts\EventStore\Domain\IntegrationEvent;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\BillRequested;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseEvent;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<RequestBill, BillRequested>
 */
#[AsCommandHandler(RequestBill::class)]
final readonly class RequestBillHandler implements CommandHandler
{
    public function __construct(
        private TableRepository $tables,
        private IntegrationBus $bus,
    ) {}

    public function __invoke(RequestBill $command): BillRequested
    {
        $table = $this->tables->get($command->tableId);

        if (! $table->hasGuests()) {
            throw new FrontOfHouseException('This table has no guests to bill.');
        }

        $this->bus->publish(IntegrationEvent::named(FrontOfHouseEvent::BILL_REQUESTED, [
            'tableId' => (string) $table->id(),
        ]));

        return new BillRequested($command->tableId);
    }
}
