<?php

return [
    /*
     * How the processors and projectors hear about new events.
     * sync: in the request that appended them, right after its transaction commits.
     * worker: not at all; `php artisan <context>:consume` reads the log and waits for more.
     */
    'mode' => env('EVENT_CONSUMPTION', 'sync'),

    /*
     * How long a consumer lock lives without being released, in seconds. It frees a lock whose
     * process died, so it must be longer than the longest pass of a consumer.
     * Locks need a cache store shared by every process: database or redis, not array.
     */
    'lock_seconds' => (int) env('EVENT_CONSUMER_LOCK_SECONDS', 120),
];
