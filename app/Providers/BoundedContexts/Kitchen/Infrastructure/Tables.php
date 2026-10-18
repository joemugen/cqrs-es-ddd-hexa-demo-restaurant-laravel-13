<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure;

/**
 * Table names. Migrations keep the literal name on purpose: a migration describes the schema as it was, and must not move when a constant is renamed.
 */
final class Tables
{
    public const DISHES = 'kitchen_dishes';

    public const SCREEN_TICKETS = 'kitchen_screen_tickets';

    public const SCREEN_LINES = 'kitchen_screen_lines';
}
