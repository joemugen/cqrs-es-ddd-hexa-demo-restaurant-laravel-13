<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure;

/**
 * Table names. Migrations keep the literal name on purpose: a migration describes the schema as it was, and must not move when a constant is renamed.
 */
final class Tables
{
    public const IDEMPOTENCY_KEYS = 'idempotency_keys';
}
