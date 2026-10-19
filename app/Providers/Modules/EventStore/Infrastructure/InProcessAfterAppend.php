<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Infrastructure;

use App\Providers\Modules\EventStore\Application\AfterAppend;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

final class InProcessAfterAppend implements AfterAppend
{
    /** @var list<Closure(): void> */
    private array $listeners = [];

    private bool $running = false;

    private bool $pending = false;

    public function listen(Closure $listener): void
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
    private function run(Closure $listener): void
    {
        try {
            DB::transaction($listener);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
