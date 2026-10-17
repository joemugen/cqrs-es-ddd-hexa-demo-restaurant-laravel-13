<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application;

final readonly class FireDishes
{
    public function __construct(
        public string $ticketId,
        public string $course,
    ) {}
}
