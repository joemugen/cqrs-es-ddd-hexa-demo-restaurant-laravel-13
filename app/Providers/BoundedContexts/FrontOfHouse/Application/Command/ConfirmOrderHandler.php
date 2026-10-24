<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\ConfirmOrderAccepted;
use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<ConfirmOrder, ConfirmOrderAccepted>
 */
#[AsCommandHandler(ConfirmOrder::class)]
final readonly class ConfirmOrderHandler implements CommandHandler
{
    public function __construct(
        private OrderFulfilmentRepository $fulfilments,
    ) {}

    public function __invoke(ConfirmOrder $command): ConfirmOrderAccepted
    {
        if ($this->fulfilments->exists($command->tableOrderId)) {
            $fulfilment = $this->fulfilments->get($command->tableOrderId);
            $fulfilment->confirm();
            $this->fulfilments->save($fulfilment);
        }

        return new ConfirmOrderAccepted($command->tableOrderId);
    }
}
