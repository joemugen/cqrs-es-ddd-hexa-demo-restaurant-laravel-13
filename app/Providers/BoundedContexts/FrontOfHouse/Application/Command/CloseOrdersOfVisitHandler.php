<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\OrdersClosed;
use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<CloseOrdersOfVisit, OrdersClosed>
 */
#[AsCommandHandler(CloseOrdersOfVisit::class)]
final readonly class CloseOrdersOfVisitHandler implements CommandHandler
{
    public function __construct(
        private OrderFulfilmentRepository $fulfilments,
        private ContractPublisher $contracts,
    ) {}

    public function __invoke(CloseOrdersOfVisit $command): OrdersClosed
    {
        foreach ($this->fulfilments->openOf($command->visitId) as $fulfilment) {
            $fulfilment->close();
            $this->fulfilments->save($fulfilment);
            $this->contracts->publish($fulfilment->releaseEvents());
        }

        return new OrdersClosed($command->visitId);
    }
}
