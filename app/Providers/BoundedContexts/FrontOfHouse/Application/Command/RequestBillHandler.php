<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\RequestBillAccepted;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<RequestBill, RequestBillAccepted>
 */
#[AsCommandHandler(RequestBill::class)]
final readonly class RequestBillHandler implements CommandHandler
{
    public function __construct(
        private TableRepository $tables,
        private ContractPublisher $contracts,
    ) {}

    public function __invoke(RequestBill $command): RequestBillAccepted
    {
        $table = $this->tables->get($command->tableId);
        $table->requestBill();
        $this->contracts->publish($table->releaseEvents());

        return new RequestBillAccepted($command->tableId);
    }
}
