<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Processors;

use App\Providers\BoundedContexts\Billing\Application\Command\OpenBill;
use App\Providers\BoundedContexts\Billing\Application\Command\ReceiveDish;
use App\Providers\BoundedContexts\Billing\Application\Command\SettleBill;
use App\Providers\BoundedContexts\Billing\Infrastructure\Bus\BillingCommandBus;
use App\Providers\BoundedContexts\Billing\Infrastructure\Consumed\BillingBillOpened;
use App\Providers\BoundedContexts\Billing\Infrastructure\Consumed\BillingBillPaid;
use App\Providers\BoundedContexts\Billing\Infrastructure\Consumed\BillingDishPlacedOnBill;
use App\Providers\Modules\EventStore\Application\ConsumedMessages;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Domain\StoredEvent;
use App\Providers\Modules\SharedKernel\Application\Command;
use App\Providers\Modules\SharedKernel\Application\Processor;
use App\Providers\Modules\SharedKernel\Infrastructure\Causation;
use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;

/**
 * The saga between the ledger of a cover and its bill, two consistency boundaries that agree through facts. The ledger decides
 * where a dish goes: the bill is opened with the dishes placed on it, and receives the ones that come after. The ledger knows the
 * bill only by what it was told, so it may place a dish on a bill that was paid a moment before: the bill, which is immutable once
 * paid, writes the dish off. Nobody undoes anything: the loss is the rule.
 */
final class BillFollowsLedger implements Processor
{
    public const CHECKPOINT = 'billing.follows-ledger';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
        private BillingCommandBus $bus,
        private ConsumedMessages $messages,
    ) {}

    public function hasWork(): bool
    {
        return $this->events->hasEventsAfter($this->checkpoints->get(self::CHECKPOINT));
    }

    public function consume(): int
    {
        $handled = 0;
        $position = $this->checkpoints->get(self::CHECKPOINT);

        foreach ($this->events->readAllFrom($position) as $event) {
            if ($this->react($event)) {
                $handled++;
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);

        return $handled;
    }

    private function react(StoredEvent $event): bool
    {
        $message = $this->messages->read($event, self::class);

        $command = match (true) {
            $message instanceof BillingBillOpened => new OpenBill($message->billId, $message->coverRef, $message->dishes),
            $message instanceof BillingDishPlacedOnBill => new ReceiveDish($message->billId, $message->dish),
            $message instanceof BillingBillPaid => new SettleBill($message->coverRef, $message->billId),
            default => null,
        };

        if ($command === null) {
            return false;
        }

        $this->dispatch($event, $command);

        return true;
    }

    private function dispatch(StoredEvent $event, Command $command): void
    {
        Correlation::resume($event->correlationId);
        Causation::resume($event->position);
        $this->bus->dispatch($command);
    }
}
