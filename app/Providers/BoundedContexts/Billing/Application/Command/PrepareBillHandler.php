<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Application\Command\Response\PrepareBillAccepted;
use App\Providers\BoundedContexts\Billing\Application\CoverLedgerRepository;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<PrepareBill, PrepareBillAccepted>
 */
#[AsCommandHandler(PrepareBill::class)]
final readonly class PrepareBillHandler implements CommandHandler
{
    public function __construct(
        private CoverLedgerRepository $ledgers,
        private ContractPublisher $contracts,
    ) {}

    /**
     * The ledger places the dishes on a bill and says so: the bill itself is opened by what follows that fact.
     */
    public function __invoke(PrepareBill $command): ?PrepareBillAccepted
    {
        $ledger = $this->ledgers->get($command->coverRef);
        $bill = $ledger->openBill($command->billId);

        if ($bill === null) {
            return null;
        }

        $this->ledgers->save($ledger);
        $this->contracts->publish($ledger->releaseEvents());

        return new PrepareBillAccepted($bill);
    }
}
