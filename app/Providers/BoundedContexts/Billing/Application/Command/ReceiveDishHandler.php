<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Application\BillRepository;
use App\Providers\BoundedContexts\Billing\Application\Command\Response\ReceiveDishAccepted;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<ReceiveDish, ReceiveDishAccepted>
 */
#[AsCommandHandler(ReceiveDish::class)]
final readonly class ReceiveDishHandler implements CommandHandler
{
    public function __construct(
        private BillRepository $bills,
        private ContractPublisher $contracts,
    ) {}

    public function __invoke(ReceiveDish $command): ReceiveDishAccepted
    {
        $bill = $this->bills->get($command->billId);

        $bill->receive($command->dish);
        $this->bills->save($bill);
        $this->contracts->publish($bill->releaseEvents());

        return new ReceiveDishAccepted($command->billId);
    }
}
