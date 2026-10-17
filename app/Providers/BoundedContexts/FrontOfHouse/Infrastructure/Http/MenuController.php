<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\ListMenu;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Query\ListMenuHandler;
use Illuminate\Http\JsonResponse;

final class MenuController
{
    public function __invoke(ListMenuHandler $handler): JsonResponse
    {
        return response()->json($handler->handle(new ListMenu));
    }
}
