<?php

describe('the sqlite connection', function (): void {
    it('waits for a lock instead of failing at once, since the API and the workers write to the same file', function (): void {
        expect((int) config('database.connections.sqlite.busy_timeout'))->toBeGreaterThanOrEqual(1000);
    });

    it('lets readers go on while a writer works', function (): void {
        expect(strtolower((string) config('database.connections.sqlite.journal_mode')))->toBe('wal');
    });

    it('takes the write lock when a transaction begins, so a read cannot be upgraded into a failure', function (): void {
        expect(strtoupper((string) config('database.connections.sqlite.transaction_mode')))->toBe('IMMEDIATE');
    });
});
