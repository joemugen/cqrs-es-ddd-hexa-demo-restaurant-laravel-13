<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Query\Response;

use App\Providers\Modules\SharedKernel\Application\QueryResponse;

final readonly class MenuView implements QueryResponse
{
    /**
     * @param  list<MenuEntry>  $rows
     */
    public function __construct(public array $rows) {}

    /**
     * @return list<MenuEntry>
     */
    public function jsonSerialize(): array
    {
        return $this->rows;
    }
}
