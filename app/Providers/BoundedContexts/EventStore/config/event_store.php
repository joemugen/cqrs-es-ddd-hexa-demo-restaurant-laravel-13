<?php

return [
    'events_table' => 'events',
    'checkpoints_table' => 'checkpoints',

    /*
     * How the processors and projectors hear about new events.
     * sync: in the request that appended them, right after its transaction commits.
     * worker: not at all; `php artisan <context>:consume` reads the log and waits for more.
     */
    'consumption' => env('EVENT_CONSUMPTION', 'sync'),
];
