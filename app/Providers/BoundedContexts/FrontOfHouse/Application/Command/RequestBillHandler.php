<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\EventStore\Application\IntegrationBus;
use App\Providers\BoundedContexts\EventStore\Domain\IntegrationEvent;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\UnitOfWork;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<RequestBill>
 */
final readonly class RequestBillHandler implements CommandHandler
{
    public function __construct(
        private TableRepository $tables,
        private IntegrationBus $bus,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @return array{tableId: string, accepted: true}
     */
    public function handle(Command $command): array
    {
        assert($command instanceof RequestBill);

        $this->unitOfWork->execute(function () use ($command): void {
            $table = $this->tables->get($command->tableId);

            if (! $table->hasGuests()) {
                throw new FrontOfHouseException('This table has no guests to bill.');
            }

            $this->bus->publish(IntegrationEvent::named('front_of_house.bill_requested', [
                'tableId' => (string) $table->id(),
            ]));
        });

        return [
            'tableId' => (string) $command->tableId,
            'accepted' => true,
        ];
    }
}
