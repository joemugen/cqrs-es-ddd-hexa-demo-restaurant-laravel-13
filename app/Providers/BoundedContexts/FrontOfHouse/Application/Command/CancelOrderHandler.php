<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\OrderCancelled;
use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FulfilmentStatus;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
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
        private ContractPublisher $contracts,
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

        $fulfilment->cancel($command->reason);
        $this->fulfilments->save($fulfilment);
        $this->contracts->publish($fulfilment->releaseEvents());

        return new OrderCancelled($command->tableOrderId);
    }
}
