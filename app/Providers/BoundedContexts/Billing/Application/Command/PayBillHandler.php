<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Application\BillRepository;
use App\Providers\BoundedContexts\Billing\Application\Command\Response\PaymentAccepted;
use App\Providers\BoundedContexts\Billing\Application\UnitOfWork;
use App\Providers\BoundedContexts\Billing\Domain\BillingEvent;
use App\Providers\BoundedContexts\EventStore\Application\IntegrationBus;
use App\Providers\BoundedContexts\EventStore\Domain\IntegrationEvent;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<PayBill, PaymentAccepted>
 */
final readonly class PayBillHandler implements CommandHandler
{
    public function __construct(
        private BillRepository $bills,
        private IntegrationBus $bus,
        private UnitOfWork $unitOfWork,
    ) {}

    public function handle(Command $command): PaymentAccepted
    {
        assert($command instanceof PayBill);

        $this->unitOfWork->execute(function () use ($command): void {
            $bill = $this->bills->get($command->billId);
            $bill->pay($command->amountCents, $command->tipCents);
            $this->bills->save($bill);

            if ($bill->isPaid()) {
                $this->bus->publish(IntegrationEvent::named(BillingEvent::BILL_PAID, [
                    'coverRef' => (string) $bill->coverRef(),
                    'billId' => (string) $bill->id(),
                ]));
            }
        });

        return new PaymentAccepted($command->billId);
    }
}
