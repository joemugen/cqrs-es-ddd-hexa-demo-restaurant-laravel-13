<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application;

use JsonSerializable;

/**
 * What a query handler answers: a read model view, never an aggregate.
 */
interface QueryResponse extends JsonSerializable {}
