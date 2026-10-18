<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Projection;

use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Eventing\EventConsumers;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProjectToServeList implements ShouldQueue
{
    use Queueable;

    public function handle(EventConsumers $consumers): void
    {
        $consumers->make(ToServeProjector::class)->consume();
    }
}
