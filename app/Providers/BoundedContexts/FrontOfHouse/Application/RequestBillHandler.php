<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

use App\Providers\BoundedContexts\EventStore\Application\IntegrationBus;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;

final readonly class RequestBillHandler
{
    public function __construct(
        private TableRepository $tables,
        private IntegrationBus $bus,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @return array{tableId: string, accepted: true}
     */
    public function handle(RequestBill $command): array
    {
        $this->unitOfWork->execute(function () use ($command): void {
            $table = $this->tables->get($command->tableId);

            if (! $table->hasGuests()) {
                throw new FrontOfHouseException('This table has no guests to bill.');
            }

            $this->bus->publish('front_of_house.bill_requested', [
                'tableId' => $table->id(),
            ]);
        });

        return [
            'tableId' => $command->tableId,
            'accepted' => true,
        ];
    }
}
