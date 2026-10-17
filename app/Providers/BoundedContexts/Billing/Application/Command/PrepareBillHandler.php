<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Application\BillRepository;
use App\Providers\BoundedContexts\Billing\Application\ServedLedger;
use App\Providers\BoundedContexts\Billing\Application\UnitOfWork;
use App\Providers\BoundedContexts\Billing\Domain\Bill;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<PrepareBill>
 */
final readonly class PrepareBillHandler implements CommandHandler
{
    public function __construct(
        private BillRepository $bills,
        private ServedLedger $served,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @return array{billId: string, accepted: true}|null
     */
    public function handle(Command $command): ?array
    {
        assert($command instanceof PrepareBill);

        return $this->unitOfWork->execute(function () use ($command): ?array {
            $open = $this->bills->openFor($command->coverRef);

            if ($open !== null) {
                return [
                    'billId' => (string) $open->id(),
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
                fn (ServedDish $line): SourceKey => $line->sourceKey,
                $bill->lines(),
            ));

            return [
                'billId' => (string) $bill->id(),
                'accepted' => true,
            ];
        });
    }
}
