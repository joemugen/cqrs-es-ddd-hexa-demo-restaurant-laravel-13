<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\DishPutAtThePass;
use App\Providers\BoundedContexts\FrontOfHouse\Application\PassRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePass;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<PutDishAtThePass, DishPutAtThePass>
 */
#[AsCommandHandler(PutDishAtThePass::class)]
final readonly class PutDishAtThePassHandler implements CommandHandler
{
    public function __construct(private PassRepository $passes) {}

    /**
     * A dish is put once: hearing again that it is ready must not make a dish that was served wait again.
     */
    public function __invoke(PutDishAtThePass $command): DishPutAtThePass
    {
        $id = DishAtThePassId::forLine($command->ticketId, $command->lineId);

        if (! $this->passes->exists($id)) {
            $this->passes->save(new DishAtThePass(
                $id,
                $command->tableId,
                $command->dishName,
                $command->quantity,
                $command->ticketId,
                $command->lineId,
                false,
            ));
        }

        return new DishPutAtThePass($id);
    }
}
