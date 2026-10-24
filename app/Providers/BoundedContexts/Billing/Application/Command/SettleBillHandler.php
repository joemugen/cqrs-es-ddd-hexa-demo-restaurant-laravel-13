<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Application\Command\Response\SettleBillAccepted;
use App\Providers\BoundedContexts\Billing\Application\CoverLedgerRepository;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<SettleBill, SettleBillAccepted>
 */
#[AsCommandHandler(SettleBill::class)]
final readonly class SettleBillHandler implements CommandHandler
{
    public function __construct(
        private CoverLedgerRepository $ledgers,
    ) {}

    public function __invoke(SettleBill $command): SettleBillAccepted
    {
        $ledger = $this->ledgers->get($command->coverRef);

        $ledger->settle($command->billId);
        $this->ledgers->save($ledger);

        return new SettleBillAccepted($command->billId);
    }
}
