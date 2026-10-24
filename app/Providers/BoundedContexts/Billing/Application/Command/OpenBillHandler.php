<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Application\BillRepository;
use App\Providers\BoundedContexts\Billing\Application\Command\Response\OpenBillAccepted;
use App\Providers\BoundedContexts\Billing\Domain\Bill;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<OpenBill, OpenBillAccepted>
 */
#[AsCommandHandler(OpenBill::class)]
final readonly class OpenBillHandler implements CommandHandler
{
    public function __construct(
        private BillRepository $bills,
    ) {}

    public function __invoke(OpenBill $command): OpenBillAccepted
    {
        if (! $this->bills->exists($command->billId)) {
            $this->bills->save(Bill::prepare($command->billId, $command->coverRef, $command->dishes));
        }

        return new OpenBillAccepted($command->billId);
    }
}
