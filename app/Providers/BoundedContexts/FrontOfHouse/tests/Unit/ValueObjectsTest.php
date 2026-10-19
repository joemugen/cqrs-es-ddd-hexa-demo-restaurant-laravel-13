<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;
use App\Providers\Modules\SharedKernel\Domain\InvalidValue;

describe('a dish name', function (): void {
    it('accepts a menu name', function (): void {
        expect((string) new DishName('Steak frites'))->toBe('Steak frites');
    });

    it('cannot be blank', function (string $name): void {
        expect(fn () => new DishName($name))->toThrow(InvalidValue::class, 'A dish name cannot be blank.');
    })->with(['', '   ']);

    it('cannot exceed 80 characters', function (): void {
        expect(fn () => new DishName(str_repeat('a', 81)))
            ->toThrow(InvalidValue::class, 'A dish name cannot exceed 80 characters.');
    });

    it('cannot hold a line break', function (): void {
        expect(fn () => new DishName("Steak\nfrites"))
            ->toThrow(InvalidValue::class, 'A dish name cannot contain control characters.');
    });
});

describe('a table label', function (): void {
    it('accepts a floor plan label', function (): void {
        expect((string) new TableLabel('Window 4'))->toBe('Window 4');
    });

    it('cannot be blank', function (): void {
        expect(fn () => new TableLabel(' '))->toThrow(InvalidValue::class, 'A table label cannot be blank.');
    });

    it('cannot exceed 40 characters', function (): void {
        expect(fn () => new TableLabel(str_repeat('a', 41)))
            ->toThrow(InvalidValue::class, 'A table label cannot exceed 40 characters.');
    });
});

describe('a table id', function (): void {
    it('follows the identifier rules', function (): void {
        expect(fn () => new TableId('table 4'))->toThrow(InvalidValue::class);
    });
});
