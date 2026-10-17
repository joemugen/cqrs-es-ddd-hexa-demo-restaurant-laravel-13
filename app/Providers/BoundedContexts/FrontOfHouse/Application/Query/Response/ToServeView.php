<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Query\Response;

use App\Providers\BoundedContexts\SharedKernel\Application\QueryResponse;

final readonly class ToServeView implements QueryResponse
{
    /**
     * @param  list<ToServeEntry>  $rows
     */
    public function __construct(public array $rows) {}

    /**
     * @return list<ToServeEntry>
     */
    public function jsonSerialize(): array
    {
        return $this->rows;
    }
}
