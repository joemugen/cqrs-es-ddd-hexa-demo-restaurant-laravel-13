<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure;

use Closure;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * The id that ties together everything one request caused: its log lines, the events it appended, the work
 * a worker does when it reacts to those events, a queued job. It lives in the Laravel Context, which adds it to
 * every log line and hands it to queued jobs by itself.
 */
final class Correlation
{
    public const HEADER = 'X-Correlation-Id';

    private const KEY = 'correlation_id';

    public static function current(): ?string
    {
        $id = Context::get(self::KEY);

        return is_string($id) ? $id : null;
    }

    /**
     * Use the incoming id when it is fit for a log line and a header, a fresh UUID otherwise.
     */
    public static function begin(?string $incoming): string
    {
        $id = $incoming !== null && preg_match('/^[A-Za-z0-9._-]{1,64}$/D', $incoming) === 1
            ? $incoming
            : (string) Str::uuid();

        Context::add(self::KEY, $id);

        return $id;
    }

    /**
     * The current id, or a new one when work starts outside a request, from a command line or a worker.
     */
    public static function ensure(): string
    {
        return self::current() ?? self::begin(null);
    }

    /**
     * Carry on with the id stored on an event. An event stored before ids existed starts a new trace.
     */
    public static function resume(?string $stored): void
    {
        self::begin($stored);
    }

    /**
     * Run $work and put back the id that was current before, so one event's id never leaks into the next.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public static function within(Closure $work): mixed
    {
        $previous = self::current();

        try {
            return $work();
        } finally {
            $previous === null ? Context::forget(self::KEY) : Context::add(self::KEY, $previous);
        }
    }
}
