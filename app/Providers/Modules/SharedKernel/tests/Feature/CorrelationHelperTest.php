<?php

use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;
use Illuminate\Support\Facades\Context;

describe('the correlation helper', function (): void {
    it('keeps an acceptable id and generates one for anything else', function (?string $incoming, bool $kept): void {
        $id = Correlation::begin($incoming);

        expect($kept ? $id : preg_match('/^[0-9a-f-]{36}$/', $id))->toBe($kept ? $incoming : 1);
    })->with([
        'a slug' => ['checkout-42', true],
        'dots and underscores' => ['a.b_c', true],
        'nothing' => [null, false],
        'blank' => ['', false],
        'spaces' => ['a b', false],
        'too long' => [str_repeat('x', 65), false],
    ]);

    it('makes sure there is one without replacing the current one', function (): void {
        Correlation::begin('first');

        expect(Correlation::ensure())->toBe('first');

        Context::forget('correlation_id');

        expect(Correlation::ensure())->toBe(Correlation::current());
    });

    it('resumes a stored id, or starts a new one for an event stored without it', function (): void {
        Correlation::resume('stored');
        expect(Correlation::current())->toBe('stored');

        Correlation::resume(null);
        expect(Correlation::current())->not->toBe('stored')->and(Correlation::current())->toBeString();
    });

    it('restores what was there before a scope ends, even when it fails', function (): void {
        Correlation::begin('outer');

        try {
            Correlation::within(function (): void {
                Correlation::resume('inner');

                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
        }

        expect(Correlation::current())->toBe('outer');
    });
});
