<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Application\Command\Response\RecordServedDishAccepted;
use App\Providers\BoundedContexts\Billing\Application\CoverLedgerRepository;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<RecordServedDish, RecordServedDishAccepted>
 */
#[AsCommandHandler(RecordServedDish::class)]
final readonly class RecordServedDishHandler implements CommandHandler
{
    public function __construct(
        private CoverLedgerRepository $ledgers,
        private ContractPublisher $contracts,
    ) {}

    public function __invoke(RecordServedDish $command): RecordServedDishAccepted
    {
        $ledger = $this->ledgers->get($command->coverRef);

        $ledger->record($command->dish, $command->position);
        $this->ledgers->save($ledger);
        $this->contracts->publish($ledger->releaseEvents());

        return new RecordServedDishAccepted($command->dish->sourceKey);
    }
}
