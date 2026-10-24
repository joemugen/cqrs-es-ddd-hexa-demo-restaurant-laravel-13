<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Http;

use App\Providers\Modules\SharedKernel\Application\Exception\Refusal;
use App\Providers\Modules\SharedKernel\Domain\DomainException;
use Throwable;

/**
 * What HTTP says of a refusal. The domain only says why it refuses (a category, or a class of its own); this is where that
 * becomes a status. A context adds its refusals that a category does not fit, and what is registered last is asked first.
 */
final class RefusalStatuses
{
    /**
     * @var array<class-string, int>
     */
    private array $statuses = [];

    /**
     * @param  array<class-string, int>  $statuses  a class or a category, and the status that says it
     */
    public function register(array $statuses): void
    {
        $this->statuses = $statuses + $this->statuses;
    }

    public function of(Throwable $failure): int
    {
        return $this->ofClass($failure::class);
    }

    /**
     * @param  class-string  $class
     */
    public function ofClass(string $class): int
    {
        if (! is_subclass_of($class, DomainException::class) && ! is_subclass_of($class, Refusal::class)) {
            return 500;
        }

        foreach ($this->statuses as $type => $status) {
            if (is_a($class, $type, true)) {
                return $status;
            }
        }

        return 500;
    }
}
