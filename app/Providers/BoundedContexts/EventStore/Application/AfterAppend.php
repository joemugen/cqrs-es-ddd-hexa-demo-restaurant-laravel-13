<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\EventStore\Application;

interface AfterAppend
{
    /**
     * Wake projectors and processors after any fact has been appended.
     *
     * @param  callable(): void  $listener
     */
    public function listen(callable $listener): void;

    public function notify(): void;
}
