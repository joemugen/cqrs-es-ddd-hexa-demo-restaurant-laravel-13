<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure;

use Closure;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * The id of the message that directly causes whatever is appended now: the id of the command being handled,
 * or the position of the event a processor reacts to. The correlation id names the whole chain, this one names the parent link.
 */
final class Causation
{
    private const KEY = 'causation_id';

    public static function current(): ?string
    {
        $id = Context::get(self::KEY);

        return is_string($id) ? $id : null;
    }

    /**
     * A command starts here: it gets an id, and what it appends points to it.
     */
    public static function begin(): string
    {
        $id = (string) Str::uuid();

        Context::add(self::KEY, $id);

        return $id;
    }

    /**
     * A processor reacts to the event at $position: what it dispatches points to that event.
     */
    public static function resume(int $position): void
    {
        Context::add(self::KEY, (string) $position);
    }

    /**
     * Run $work and put back the cause that was current before, so one message never becomes the cause of the next.
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
