<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Application\BillRepository;
use App\Providers\BoundedContexts\Billing\Application\Command\Response\PayBillAccepted;
use App\Providers\BoundedContexts\Billing\Domain\Bill;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;
use App\Providers\Modules\SharedKernel\Application\Concurrency\PreconditionFailed;

/**
 * @implements CommandHandler<PayBill, PayBillAccepted>
 */
#[AsCommandHandler(PayBill::class)]
final readonly class PayBillHandler implements CommandHandler
{
    public function __construct(
        private BillRepository $bills,
        private ContractPublisher $contracts,
    ) {}

    public function __invoke(PayBill $command): PayBillAccepted
    {
        $bill = $this->bills->get($command->billId);
        $this->ensureTheGuestsSawTheCurrentNote($bill, $command);
        $bill->pay($command->amountCents, $command->tipCents);
        $this->bills->save($bill);

        $this->contracts->publish($bill->releaseEvents());

        return new PayBillAccepted($command->billId);
    }

    /**
     * The payment is of a note the guests saw: if a dish joined the bill since, it is refused and the note is presented again.
     */
    private function ensureTheGuestsSawTheCurrentNote(Bill $bill, PayBill $command): void
    {
        if ($command->seen->value !== $bill->version()) {
            throw new PreconditionFailed("The bill changed since the guests saw it: it is at version {$bill->version()}, not {$command->seen->value}. Present it again.");
        }
    }
}
