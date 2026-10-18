<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

/**
 * What the floor plan shows for a table.
 */
final class TableStatus
{
    public const OCCUPIED = 'occupied';

    public const FREE = 'free';
}
