<?php

use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\TableLabel;
use App\Providers\Modules\SharedKernel\Domain\InvalidValue;

describe('a dish name', function (): void {
    it('accepts a recipe name', function (): void {
        expect((string) new DishName('Green salad'))->toBe('Green salad');
    });

    it('cannot be blank, overlong or hold control characters', function (string $name): void {
        expect(fn () => new DishName($name))->toThrow(InvalidValue::class);
    })->with(['', ' ', "Green\tsalad"]);

    it('cannot exceed 80 characters', function (): void {
        expect(fn () => new DishName(str_repeat('a', 81)))
            ->toThrow(InvalidValue::class, 'A dish name cannot exceed 80 characters.');
    });
});

describe('a table label', function (): void {
    it('cannot be blank or exceed 40 characters', function (string $label): void {
        expect(fn () => new TableLabel($label))->toThrow(InvalidValue::class);
    })->with(['', str_repeat('a', 41)]);
});

describe('a ticket id', function (): void {
    it('follows the identifier rules', function (): void {
        expect(fn () => new KitchenTicketId('ticket/1'))->toThrow(InvalidValue::class);
    });
});
