<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Concurrency;

use App\Providers\Modules\SharedKernel\Application\Exception\Refusal;

/**
 * Somebody else changed the aggregate between the moment it was read and the moment it was saved.
 */
final class ConcurrencyConflict extends Refusal implements StaleVersion {}
