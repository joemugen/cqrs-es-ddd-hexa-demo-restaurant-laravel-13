<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Projection;

/**
 * What the kitchen screen shows for a dish.
 */
final class LineStatus
{
    public const PENDING = 'pending';

    public const FIRED = 'fired';

    public const READY = 'ready';
}
