<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\SharedKernel\Application\Command;

final readonly class OpenKitchenTicket implements Command
{
    /**
     * @param  list<array{lineId: string, dishName: string, station: string, cookingMinutes: int, quantity: int, course: string, allergens: list<string>}>  $lines
     */
    public function __construct(
        public string $ticketId,
        public string $partyRef,
        public string $tableLabel,
        public array $lines,
    ) {}
}
