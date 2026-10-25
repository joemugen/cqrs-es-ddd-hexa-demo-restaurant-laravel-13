<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\ServeDishAccepted;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\ServeDish;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use Illuminate\Http\JsonResponse;
use LogicException;

final class ServeDishController
{
    public function __invoke(string $toServeId, FrontOfHouseCommandBus $bus): JsonResponse
    {
        $served = $bus->dispatch(new ServeDish(new DishAtThePassId($toServeId)));

        if (! $served instanceof ServeDishAccepted) {
            throw new LogicException('The handler of this command answers with a ServeDishAccepted.');
        }

        return response()->json([
            'toServeId' => (string) $served->dishAtThePassId,
            'accepted' => true,
        ]);
    }
}
