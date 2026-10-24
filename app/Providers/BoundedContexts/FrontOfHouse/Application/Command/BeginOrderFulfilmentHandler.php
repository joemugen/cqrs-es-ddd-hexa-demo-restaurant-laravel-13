<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\BeginOrderFulfilmentAccepted;
use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\OrderFulfilment;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<BeginOrderFulfilment, BeginOrderFulfilmentAccepted>
 */
#[AsCommandHandler(BeginOrderFulfilment::class)]
final readonly class BeginOrderFulfilmentHandler implements CommandHandler
{
    public function __construct(
        private OrderFulfilmentRepository $fulfilments,
    ) {}

    public function __invoke(BeginOrderFulfilment $command): BeginOrderFulfilmentAccepted
    {
        if (! $this->fulfilments->exists($command->tableOrderId)) {
            $this->fulfilments->save(OrderFulfilment::begin($command->tableOrderId, $command->tableId, $command->visitId, $command->requestPosition));
        }

        return new BeginOrderFulfilmentAccepted($command->tableOrderId);
    }
}
