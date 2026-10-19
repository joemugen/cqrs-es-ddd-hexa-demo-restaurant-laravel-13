<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Application\BillRepository;
use App\Providers\BoundedContexts\Billing\Application\Command\Response\BillPrepared;
use App\Providers\BoundedContexts\Billing\Application\ServedLedger;
use App\Providers\BoundedContexts\Billing\Domain\Bill;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<PrepareBill, BillPrepared>
 */
#[AsCommandHandler(PrepareBill::class)]
final readonly class PrepareBillHandler implements CommandHandler
{
    public function __construct(
        private BillRepository $bills,
        private ServedLedger $served,
    ) {}

    public function __invoke(PrepareBill $command): ?BillPrepared
    {
        $open = $this->bills->openFor($command->coverRef);

        if ($open !== null) {
            return new BillPrepared($open->id());
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

        return new BillPrepared($bill->id());
    }
}
