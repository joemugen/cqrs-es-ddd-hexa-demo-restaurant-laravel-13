<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Application\BillRepository;
use App\Providers\BoundedContexts\Billing\Application\Command\Response\ServedDishRecorded;
use App\Providers\BoundedContexts\Billing\Application\ServedLedger;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<RecordServedDish, ServedDishRecorded>
 */
#[AsCommandHandler(RecordServedDish::class)]
final readonly class RecordServedDishHandler implements CommandHandler
{
    public function __construct(
        private BillRepository $bills,
        private ServedLedger $served,
    ) {}

    public function __invoke(RecordServedDish $command): ServedDishRecorded
    {
        $this->served->record($command->coverRef, $command->dish, $command->position);
        $bill = $this->bills->openFor($command->coverRef);

        if ($bill !== null) {
            $bill->receive($command->dish);
            $this->bills->save($bill);
            $this->served->attach($bill->id(), [$command->dish->sourceKey]);
        }

        return new ServedDishRecorded($command->dish->sourceKey);
    }
}
