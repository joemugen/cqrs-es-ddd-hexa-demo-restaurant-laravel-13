<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Table;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;

interface TableRepository
{
    public function get(TableId $id): Table;

    /**
     * The table that hosts the guests of a visit, if they are still there.
     */
    public function hosting(VisitId $visit): ?Table;

    public function save(Table $table): void;
}
