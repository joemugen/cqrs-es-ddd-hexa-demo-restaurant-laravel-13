<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Metrics;

enum Metric: string
{
    case Command = 'cqrs_command';
    case Query = 'cqrs_query';
    case ConsumerPass = 'cqrs_consumer_pass';
    case ConsumerEvents = 'cqrs_consumer_events';

    public function isTimed(): bool
    {
        return $this !== self::ConsumerEvents;
    }
}
