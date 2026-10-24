<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Concurrency;

use App\Providers\Modules\SharedKernel\Application\Exception\Refusal;

/**
 * The client decided on a state of the resource that is no longer the current one. It must read again and decide again,
 * so the bus never replays it.
 */
final class PreconditionFailed extends Refusal implements StaleVersion {}
