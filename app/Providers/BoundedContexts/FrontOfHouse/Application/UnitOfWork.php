<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

interface UnitOfWork
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function execute(callable $callback): mixed;
}
