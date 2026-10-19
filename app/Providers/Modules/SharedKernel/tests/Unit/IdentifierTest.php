<?php

use App\Providers\Modules\SharedKernel\Domain\Identifier;
use App\Providers\Modules\SharedKernel\Domain\InvalidValue;

final readonly class TestingIdentifier extends Identifier {}

describe('an identifier', function (): void {
    it('accepts the ids the project uses: ULIDs and slugs', function (string $id): void {
        expect((string) new TestingIdentifier($id))->toBe($id);
    })->with(['01JAK3N7Q8X2M5R9T4V6W0YZBC', 'table-4', 'order_1-2', 'a', 'A9']);

    it('refuses a blank value', function (string $id): void {
        expect(fn () => new TestingIdentifier($id))
            ->toThrow(InvalidValue::class, 'An identifier cannot be blank.');
    })->with(['', ' ', "\t\n"]);

    it('refuses a value longer than 64 characters', function (): void {
        expect(fn () => new TestingIdentifier(str_repeat('a', 65)))
            ->toThrow(InvalidValue::class, 'An identifier cannot exceed 64 characters.');
    });

    it('accepts exactly 64 characters', function (): void {
        expect((string) new TestingIdentifier(str_repeat('a', 64)))->toHaveLength(64);
    });

    it('refuses anything but letters, digits, dashes and underscores', function (string $id): void {
        expect(fn () => new TestingIdentifier($id))
            ->toThrow(InvalidValue::class, 'An identifier may only contain letters, digits, dashes and underscores.');
    })->with(['table 4', 'table/4', 'tâble', 'a:b', "id\n"]);

    it('is refused with a 422', function (): void {
        try {
            new TestingIdentifier('');
        } catch (InvalidValue $exception) {
            expect($exception->status())->toBe(422);
        }
    });
});
