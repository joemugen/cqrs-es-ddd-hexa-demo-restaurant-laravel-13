<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Http;

use App\Providers\Modules\SharedKernel\Domain\IdempotencyKey;
use Illuminate\Http\Request;

final class IdempotencyHeader
{
    public const NAME = 'Idempotency-Key';

    public static function keyOf(Request $request): ?IdempotencyKey
    {
        return $request->hasHeader(self::NAME) ? new IdempotencyKey($request->header(self::NAME)) : null;
    }
}
