<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\OrderRevisionApplied;
use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<ApplyOrderRevision, OrderRevisionApplied>
 */
#[AsCommandHandler(ApplyOrderRevision::class)]
final readonly class ApplyOrderRevisionHandler implements CommandHandler
{
    public function __construct(
        private OrderFulfilmentRepository $fulfilments,
    ) {}

    public function __invoke(ApplyOrderRevision $command): OrderRevisionApplied
    {
        if ($this->fulfilments->exists($command->tableOrderId)) {
            $fulfilment = $this->fulfilments->get($command->tableOrderId);
            $fulfilment->revise($command->revision, $command->revisionPosition);
            $this->fulfilments->save($fulfilment);
        }

        return new OrderRevisionApplied($command->tableOrderId);
    }
}
