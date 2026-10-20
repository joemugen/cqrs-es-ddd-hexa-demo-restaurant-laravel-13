<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Metrics;

use App\Providers\Modules\SharedKernel\Domain\ConcurrencyConflict;
use App\Providers\Modules\SharedKernel\Domain\DomainException;
use App\Providers\Modules\SharedKernel\Domain\PreconditionFailed;
use Throwable;

enum Outcome: string
{
    case Success = 'success';
    case Refused = 'refused';
    case Conflict = 'conflict';
    case PreconditionFailed = 'precondition_failed';
    case Failure = 'failure';

    public static function of(Throwable $failure): self
    {
        return match (true) {
            $failure instanceof ConcurrencyConflict => self::Conflict,
            $failure instanceof PreconditionFailed => self::PreconditionFailed,
            $failure instanceof DomainException => self::Refused,
            default => self::Failure,
        };
    }
}
