<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Application\BillRepository;
use App\Providers\BoundedContexts\Billing\Application\Command\Response\PayBillAccepted;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

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
        $bill->pay($command->amountCents, $command->tipCents, $command->seen);
        $this->bills->save($bill);

        $this->contracts->publish($bill->releaseEvents());

        return new PayBillAccepted($command->billId);
    }
}
