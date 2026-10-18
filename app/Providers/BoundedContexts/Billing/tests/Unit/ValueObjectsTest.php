<?php

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use App\Providers\BoundedContexts\SharedKernel\Domain\InvalidValue;

describe('a source key', function (): void {
    it('names the ticket and the line a dish came from', function (): void {
        expect((string) new SourceKey('ticket-1:ticket-1-2'))->toBe('ticket-1:ticket-1-2');
    });

    it('refuses anything that is not a ticket and a line', function (string $key): void {
        expect(fn () => new SourceKey($key))->toThrow(InvalidValue::class);
    })->with(['', 'ticket-1', 'ticket-1:', ':line-1', 'ticket 1:line-1', 'a:b:c']);

    it('cannot exceed 129 characters', function (): void {
        expect(fn () => new SourceKey(str_repeat('a', 65).':'.str_repeat('b', 64)))
            ->toThrow(InvalidValue::class, 'A source key cannot exceed 129 characters.');
    });
});

describe('a cover reference and a bill id', function (): void {
    it('follow the identifier rules', function (): void {
        expect(fn () => new CoverRef('table 4'))->toThrow(InvalidValue::class)
            ->and(fn () => new BillId(''))->toThrow(InvalidValue::class);
    });
});
