<?php

return [
    /*
     * How the processors and projectors hear about new events.
     * sync: in the request that appended them, right after its transaction commits.
     * worker: not at all; `php artisan <context>:consume` reads the log and waits for more.
     */
    'mode' => env('EVENT_CONSUMPTION', 'sync'),

    /*
     * How long the lock of a context worker lives without being renewed, in seconds. It frees the lock of a
     * worker that died, so it must be longer than the longest pass of a consumer. One worker consumes a
     * context, and the lock needs a cache store shared by every process: database or redis, not array.
     */
    'lock_seconds' => (int) env('EVENT_CONSUMER_LOCK_SECONDS', 120),
];
