<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\EventStore\Application;

use Closure;

interface AfterAppend
{
    /**
     * Wake projectors and processors after any fact has been appended.
     *
     * @param  Closure(): void  $listener
     */
    public function listen(Closure $listener): void;

    public function notify(): void;
}
