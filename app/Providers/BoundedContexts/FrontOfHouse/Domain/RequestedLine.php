<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

/**
 * What a guest asked for, before the menu has priced it.
 */
final readonly class RequestedLine
{
    public function __construct(
        public MenuItemId $menuItemId,
        public int $quantity,
        public string $course,
    ) {}
}
