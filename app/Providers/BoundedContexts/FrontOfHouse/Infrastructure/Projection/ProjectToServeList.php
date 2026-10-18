<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Projection;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProjectToServeList implements ShouldQueue
{
    use Queueable;

    public function handle(ToServeProjector $projector): void
    {
        $projector->consume();
    }
}
