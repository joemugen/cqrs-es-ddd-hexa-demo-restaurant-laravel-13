<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\OrderAwaitingRevision;
use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<MarkOrderAwaitingRevision, OrderAwaitingRevision>
 */
#[AsCommandHandler(MarkOrderAwaitingRevision::class)]
final readonly class MarkOrderAwaitingRevisionHandler implements CommandHandler
{
    public function __construct(
        private OrderFulfilmentRepository $fulfilments,
    ) {}

    public function __invoke(MarkOrderAwaitingRevision $command): OrderAwaitingRevision
    {
        if ($this->fulfilments->exists($command->tableOrderId)) {
            $fulfilment = $this->fulfilments->get($command->tableOrderId);
            $fulfilment->awaitRevision($command->lines, $command->orderPosition);
            $this->fulfilments->save($fulfilment);
        }

        return new OrderAwaitingRevision($command->tableOrderId);
    }
}
