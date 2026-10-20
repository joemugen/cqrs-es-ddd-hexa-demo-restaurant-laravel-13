<?php

use App\Providers\Modules\SharedKernel\Domain\ExpectedVersion;
use App\Providers\Modules\SharedKernel\Domain\InvalidValue;
use App\Providers\Modules\SharedKernel\Domain\PreconditionFailed;

describe('an expected version', function (): void {
    describe('read from an If-Match header', function (): void {
        it('is the version a client read, quoted or not', function (string $header, int $version): void {
            expect(ExpectedVersion::fromIfMatch($header)?->value)->toBe($version);
        })->with([
            'quoted' => ['"3"', 3],
            'bare' => ['3', 3],
            'weak validator' => ['W/"12"', 12],
            'zero' => ['"0"', 0],
            'with spaces' => [' "7" ', 7],
        ]);

        it('is absent when the client did not ask for a check', function (?string $header): void {
            expect(ExpectedVersion::fromIfMatch($header))->toBeNull();
        })->with([null, '', '   ', '*']);

        it('is refused when it is not a version', function (string $header): void {
            expect(fn () => ExpectedVersion::fromIfMatch($header))
                ->toThrow(InvalidValue::class, 'If-Match must be the version of the resource you read, such as "3".');
        })->with(['abc', '"3.5"', '-1', '"3", "4"', '"abc"']);
    });

    describe('compared with the version of the resource', function (): void {
        it('lets the command through when they are the same', function (): void {
            ExpectedVersion::fromIfMatch('"3"')?->ensureMatches(3);

            expect(true)->toBeTrue();
        });

        it('refuses a command decided on an older state, with a 412', function (): void {
            try {
                ExpectedVersion::fromIfMatch('"2"')?->ensureMatches(5);
            } catch (PreconditionFailed $failure) {
                expect($failure->status())->toBe(412)
                    ->and($failure->getMessage())->toBe('The resource changed since you read it: it is at version 5, not 2.');

                return;
            }

            throw new RuntimeException('The stale command was accepted.');
        });
    });
});
