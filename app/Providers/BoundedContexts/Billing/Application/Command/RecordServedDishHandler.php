<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Application\BillRepository;
use App\Providers\BoundedContexts\Billing\Application\Command\Response\RecordServedDishAccepted;
use App\Providers\BoundedContexts\Billing\Application\ServedLedger;
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
        private BillRepository $bills,
        private ServedLedger $served,
        private ContractPublisher $contracts,
    ) {}

    public function __invoke(RecordServedDish $command): RecordServedDishAccepted
    {
        if (! $this->served->record($command->coverRef, $command->dish, $command->position)) {
            return new RecordServedDishAccepted($command->dish->sourceKey);
        }

        $bill = $this->bills->latestFor($command->coverRef);

        if ($bill !== null) {
            $bill->receive($command->dish);
            $this->bills->save($bill);
            $bill->isPaid()
                ? $this->served->writeOff($command->dish->sourceKey)
                : $this->served->attach($bill->id(), [$command->dish->sourceKey]);
            $this->contracts->publish($bill->releaseEvents());
        }

        return new RecordServedDishAccepted($command->dish->sourceKey);
    }
}
