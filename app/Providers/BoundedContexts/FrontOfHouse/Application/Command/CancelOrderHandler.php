<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\OrderCancelled;
use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseEvent;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FulfilmentStatus;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\UnavailableLine;
use App\Providers\Modules\EventStore\Application\IntegrationBus;
use App\Providers\Modules\EventStore\Domain\IntegrationEvent;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<CancelOrder, OrderCancelled>
 */
#[AsCommandHandler(CancelOrder::class)]
final readonly class CancelOrderHandler implements CommandHandler
{
    public function __construct(
        private OrderFulfilmentRepository $fulfilments,
        private IntegrationBus $bus,
    ) {}

    public function __invoke(CancelOrder $command): OrderCancelled
    {
        if (! $this->fulfilments->exists($command->tableOrderId)) {
            return new OrderCancelled($command->tableOrderId);
        }

        $fulfilment = $this->fulfilments->get($command->tableOrderId);

        if ($fulfilment->status() === FulfilmentStatus::CANCELLED) {
            return new OrderCancelled($command->tableOrderId);
        }

        $abandoned = array_map(fn (UnavailableLine $line): string => (string) $line->lineId, $fulfilment->unavailableLines());
        $fulfilment->cancel($command->reason);
        $this->fulfilments->save($fulfilment);
        $this->bus->publish(IntegrationEvent::named(FrontOfHouseEvent::TABLE_ORDER_CANCELLED, [
            'tableOrderId' => (string) $fulfilment->id(),
            'tableId' => (string) $fulfilment->tableId(),
            'reason' => $command->reason,
            'abandonedLines' => $abandoned,
        ]));

        return new OrderCancelled($command->tableOrderId);
    }
}
