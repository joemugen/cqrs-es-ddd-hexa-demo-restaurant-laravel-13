<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Query\Response;

use App\Providers\Modules\SharedKernel\Application\QueryResponse;

final readonly class FloorPlanView implements QueryResponse
{
    /**
     * @param  list<FloorPlanRow>  $rows
     */
    public function __construct(public array $rows) {}

    /**
     * @return list<FloorPlanRow>
     */
    public function jsonSerialize(): array
    {
        return $this->rows;
    }
}
