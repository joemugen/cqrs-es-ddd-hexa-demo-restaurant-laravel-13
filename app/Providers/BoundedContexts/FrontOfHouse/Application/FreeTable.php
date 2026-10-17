<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

final readonly class FreeTable
{
    public function __construct(public string $tableId) {}
}
