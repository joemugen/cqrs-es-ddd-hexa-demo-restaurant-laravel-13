<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Metrics;

/**
 * One value ready to be read: a counter only goes up, a gauge goes both ways.
 */
final readonly class Sample
{
    /**
     * @param  array<string, string>  $labels
     */
    public function __construct(
        public string $name,
        public array $labels,
        public int|float $value,
        public bool $gauge = false,
    ) {}
}
