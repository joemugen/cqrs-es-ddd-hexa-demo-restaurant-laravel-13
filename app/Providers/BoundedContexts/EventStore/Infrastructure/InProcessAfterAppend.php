<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\EventStore\Infrastructure;

use App\Providers\BoundedContexts\EventStore\Application\AfterAppend;
use Illuminate\Support\Facades\DB;
use Throwable;

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
                    $this->run($listener);
                }
            } while ($this->pending);
        } finally {
            $this->running = false;
        }
    }

    /**
     * Each consumer commits or rolls back alone. One failure leaves the others,
     * and the write that woke them, untouched.
     */
    private function run(callable $listener): void
    {
        try {
            DB::transaction($listener);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
