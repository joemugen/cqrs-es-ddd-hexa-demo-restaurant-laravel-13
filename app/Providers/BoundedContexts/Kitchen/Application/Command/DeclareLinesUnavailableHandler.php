<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\LinesDeclaredUnavailable;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\LinesUnavailable;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<DeclareLinesUnavailable, LinesDeclaredUnavailable>
 */
#[AsCommandHandler(DeclareLinesUnavailable::class)]
final readonly class DeclareLinesUnavailableHandler implements CommandHandler
{
    public function __construct(private ContractPublisher $contracts) {}

    public function __invoke(DeclareLinesUnavailable $command): LinesDeclaredUnavailable
    {
        $this->contracts->publish([
            new LinesUnavailable($command->ticketId, $command->partyRef, $command->orderPosition, $command->lines),
        ]);

        return new LinesDeclaredUnavailable($command->ticketId);
    }
}
