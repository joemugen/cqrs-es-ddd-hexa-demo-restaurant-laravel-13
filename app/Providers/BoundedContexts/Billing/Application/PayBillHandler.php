<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application;

use App\Providers\BoundedContexts\EventStore\Application\IntegrationBus;

final readonly class PayBillHandler
{
    public function __construct(
        private BillRepository $bills,
        private IntegrationBus $bus,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @return array{billId: string, accepted: true}
     */
    public function handle(PayBill $command): array
    {
        $this->unitOfWork->execute(function () use ($command): void {
            $bill = $this->bills->get($command->billId);
            $bill->pay($command->amountCents, $command->tipCents);
            $this->bills->save($bill);

            if ($bill->isPaid()) {
                $this->bus->publish('billing.bill_paid', [
                    'coverRef' => $bill->coverRef(),
                    'billId' => $bill->id(),
                ]);
            }
        });

        return [
            'billId' => $command->billId,
            'accepted' => true,
        ];
    }
}
