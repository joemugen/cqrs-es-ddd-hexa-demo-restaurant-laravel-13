<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application;

/**
 * A projector whose read model can be dropped and folded again from the first event.
 */
interface RebuildableProjector extends Projector
{
    public function rebuild(): void;
}
