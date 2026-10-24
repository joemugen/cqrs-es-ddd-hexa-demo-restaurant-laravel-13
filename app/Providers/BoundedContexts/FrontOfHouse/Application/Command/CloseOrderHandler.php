<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\CloseOrderAccepted;
use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<CloseOrder, CloseOrderAccepted>
 */
#[AsCommandHandler(CloseOrder::class)]
final readonly class CloseOrderHandler implements CommandHandler
{
    public function __construct(
        private OrderFulfilmentRepository $fulfilments,
        private ContractPublisher $contracts,
    ) {}

    public function __invoke(CloseOrder $command): CloseOrderAccepted
    {
        if (! $this->fulfilments->exists($command->tableOrderId)) {
            return new CloseOrderAccepted($command->tableOrderId);
        }

        $fulfilment = $this->fulfilments->get($command->tableOrderId);

        $fulfilment->close();
        $this->fulfilments->save($fulfilment);
        $this->contracts->publish($fulfilment->releaseEvents());

        return new CloseOrderAccepted($command->tableOrderId);
    }
}
