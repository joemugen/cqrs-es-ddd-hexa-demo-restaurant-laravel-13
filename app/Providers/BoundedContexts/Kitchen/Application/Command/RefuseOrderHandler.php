<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\OrderRefused;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenEvent;
use App\Providers\Modules\EventStore\Application\IntegrationBus;
use App\Providers\Modules\EventStore\Domain\IntegrationEvent;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<RefuseOrder, OrderRefused>
 */
#[AsCommandHandler(RefuseOrder::class)]
final readonly class RefuseOrderHandler implements CommandHandler
{
    public function __construct(private IntegrationBus $bus) {}

    public function __invoke(RefuseOrder $command): OrderRefused
    {
        $this->bus->publish(IntegrationEvent::named(KitchenEvent::ORDER_REFUSED, [
            'tableOrderId' => (string) $command->ticketId,
            'tableId' => (string) $command->partyRef,
            'reason' => $command->reason,
        ]));

        return new OrderRefused($command->ticketId);
    }
}
