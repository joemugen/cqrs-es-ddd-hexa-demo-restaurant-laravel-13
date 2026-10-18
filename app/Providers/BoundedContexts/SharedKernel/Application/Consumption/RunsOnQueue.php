<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application\Consumption;

use Attribute;

/**
 * Marks a consumer that may lag: in sync mode an append dispatches this job instead of running it in the request.
 * A worker ignores the attribute and consumes it like any other.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class RunsOnQueue
{
    /**
     * @param  class-string  $job  a queued job with a static dispatch()
     */
    public function __construct(public string $job) {}
}
