<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Domain;

/**
 * Base of every aggregate identifier held as a string. Extend it once per concept: a TableId is not a BillId.
 */
abstract readonly class StringId extends Identifier implements AggregateId {}
