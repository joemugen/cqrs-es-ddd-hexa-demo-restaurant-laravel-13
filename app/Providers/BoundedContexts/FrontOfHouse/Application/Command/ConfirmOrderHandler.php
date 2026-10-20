<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\OrderConfirmed;
use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<ConfirmOrder, OrderConfirmed>
 */
#[AsCommandHandler(ConfirmOrder::class)]
final readonly class ConfirmOrderHandler implements CommandHandler
{
    public function __construct(
        private OrderFulfilmentRepository $fulfilments,
    ) {}

    public function __invoke(ConfirmOrder $command): OrderConfirmed
    {
        if ($this->fulfilments->exists($command->tableOrderId)) {
            $fulfilment = $this->fulfilments->get($command->tableOrderId);
            $fulfilment->confirm();
            $this->fulfilments->save($fulfilment);
        }

        return new OrderConfirmed($command->tableOrderId);
    }
}
