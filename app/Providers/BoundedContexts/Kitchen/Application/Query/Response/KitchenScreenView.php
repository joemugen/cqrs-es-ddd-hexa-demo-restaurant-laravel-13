<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Query\Response;

use App\Providers\BoundedContexts\SharedKernel\Application\QueryResponse;

final readonly class KitchenScreenView implements QueryResponse
{
    /**
     * @param  list<KitchenScreenTicket>  $rows
     */
    public function __construct(public array $rows) {}

    /**
     * @return list<KitchenScreenTicket>
     */
    public function jsonSerialize(): array
    {
        return $this->rows;
    }
}
