<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Table;

interface TableRepository
{
    public function get(string $id): Table;

    public function save(Table $table): void;
}
