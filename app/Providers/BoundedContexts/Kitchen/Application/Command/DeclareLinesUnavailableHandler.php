<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\LinesDeclaredUnavailable;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenEvent;
use App\Providers\BoundedContexts\Kitchen\Domain\UnavailableLine;
use App\Providers\Modules\EventStore\Application\IntegrationBus;
use App\Providers\Modules\EventStore\Domain\IntegrationEvent;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<DeclareLinesUnavailable, LinesDeclaredUnavailable>
 */
#[AsCommandHandler(DeclareLinesUnavailable::class)]
final readonly class DeclareLinesUnavailableHandler implements CommandHandler
{
    public function __construct(private IntegrationBus $bus) {}

    public function __invoke(DeclareLinesUnavailable $command): LinesDeclaredUnavailable
    {
        $this->bus->publish(IntegrationEvent::named(KitchenEvent::LINES_UNAVAILABLE, [
            'tableOrderId' => (string) $command->ticketId,
            'tableId' => (string) $command->partyRef,
            'orderPosition' => $command->orderPosition,
            'lines' => array_map(fn (UnavailableLine $line): array => $line->toPayload(), $command->lines),
        ]));

        return new LinesDeclaredUnavailable($command->ticketId);
    }
}
