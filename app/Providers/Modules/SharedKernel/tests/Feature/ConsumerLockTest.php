<?php

use Illuminate\Support\Facades\Cache;

describe('one worker per context', function (): void {
    it('refuses a second worker for the same context', function (): void {
        $lock = Cache::lock('event-worker:kitchen', 60);
        $lock->get();

        $this->artisan('kitchen:consume', ['--stop-when-empty' => true])
            ->expectsOutputToContain('Another kitchen worker is already running')
            ->assertFailed();

        $lock->release();
    });

    it('does not stop a worker of another context', function (): void {
        $lock = Cache::lock('event-worker:kitchen', 60);
        $lock->get();

        $this->artisan('billing:consume', ['--stop-when-empty' => true])->assertSuccessful();

        $lock->release();
    });

    it('gives the context back when it finishes', function (): void {
        $this->artisan('kitchen:consume', ['--stop-when-empty' => true])->assertSuccessful();

        $lock = Cache::lock('event-worker:kitchen', 60);
        expect($lock->get())->toBeTrue();
        $lock->release();
    });

    it('gives the context back when the worker fails', function (): void {
        $lock = Cache::lock('event-worker:kitchen', 60);

        $this->artisan('kitchen:consume', ['--stop-when-empty' => true])->assertSuccessful();

        expect($lock->get())->toBeTrue();
        $lock->release();
    });

    it('refuses to start when the cache store cannot share its lock between processes, since a second worker could then run', function (): void {
        $this->app['env'] = 'local';

        $this->artisan('kitchen:consume', ['--stop-when-empty' => true])
            ->expectsOutputToContain('cannot share a lock between processes')
            ->assertFailed();
    });
});
