<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicket;

interface KitchenTicketRepository
{
    public function exists(string $id): bool;

    public function get(string $id): KitchenTicket;

    public function save(KitchenTicket $ticket): void;
}
