<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\OrdersClosed;
use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<CloseOrdersOfPaidTable, OrdersClosed>
 */
#[AsCommandHandler(CloseOrdersOfPaidTable::class)]
final readonly class CloseOrdersOfPaidTableHandler implements CommandHandler
{
    public function __construct(
        private OrderFulfilmentRepository $fulfilments,
    ) {}

    public function __invoke(CloseOrdersOfPaidTable $command): OrdersClosed
    {
        foreach ($this->fulfilments->awaitingRevisionAt($command->tableId) as $fulfilment) {
            $fulfilment->close($command->paymentPosition);
            $this->fulfilments->save($fulfilment);
        }

        return new OrdersClosed($command->tableId);
    }
}
