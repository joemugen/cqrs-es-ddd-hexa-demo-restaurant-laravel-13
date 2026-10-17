<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Domain;

use Stringable;

/**
 * The identity of an aggregate. It casts to the string stored and exposed outside.
 */
interface AggregateId extends Stringable {}
