<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application;

use App\Providers\BoundedContexts\Billing\Domain\Bill;

final readonly class PrepareBillHandler
{
    public function __construct(
        private BillRepository $bills,
        private ServedLedger $served,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @return array{billId: string, accepted: true}|null
     */
    public function handle(PrepareBill $command): ?array
    {
        return $this->unitOfWork->execute(function () use ($command): ?array {
            $open = $this->bills->openFor($command->coverRef);

            if ($open !== null) {
                return [
                    'billId' => $open->id(),
                    'accepted' => true,
                ];
            }

            $lines = $this->served->unbilled($command->coverRef);

            if ($lines === []) {
                return null;
            }

            $bill = Bill::prepare($command->billId, $command->coverRef, $lines);
            $this->bills->save($bill);
            $this->served->attach($bill->id(), array_map(
                fn ($line): string => $line->sourceKey,
                $bill->lines(),
            ));

            return [
                'billId' => $bill->id(),
                'accepted' => true,
            ];
        });
    }
}
