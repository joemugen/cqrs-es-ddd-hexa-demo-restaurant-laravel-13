<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\EventStore\Infrastructure;

use App\Providers\BoundedContexts\EventStore\Application\AfterAppend;

final class InProcessAfterAppend implements AfterAppend
{
    /** @var list<callable(): void> */
    private array $listeners = [];

    private bool $running = false;

    private bool $pending = false;

    public function listen(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function notify(): void
    {
        if ($this->running) {
            $this->pending = true;

            return;
        }

        $this->running = true;

        try {
            do {
                $this->pending = false;

                foreach ($this->listeners as $listener) {
                    $listener();
                }
            } while ($this->pending);
        } finally {
            $this->running = false;
        }
    }
}
