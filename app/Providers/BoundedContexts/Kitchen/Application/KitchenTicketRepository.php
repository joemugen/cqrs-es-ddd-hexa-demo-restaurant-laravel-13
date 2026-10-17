<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicket;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;

interface KitchenTicketRepository
{
    public function exists(KitchenTicketId $id): bool;

    public function get(KitchenTicketId $id): KitchenTicket;

    public function save(KitchenTicket $ticket): void;
}
