<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\OrdersClosed;
use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
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
    ) {}

    public function __invoke(CloseOrdersOfVisit $command): OrdersClosed
    {
        foreach ($this->fulfilments->awaitingRevisionOf($command->visitId) as $fulfilment) {
            $fulfilment->close();
            $this->fulfilments->save($fulfilment);
        }

        return new OrdersClosed($command->visitId);
    }
}
