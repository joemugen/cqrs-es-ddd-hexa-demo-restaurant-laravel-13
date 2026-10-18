<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Table;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;

describe('Table', function () {
    test('a free table seats its guests', function () {
        $table = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 0);

        $table->seat(2);

        expect($table->hasGuests())->toBeTrue()
            ->and($table->guestCount())->toBe(2)
            ->and($table->status())->toBe('occupied');
    });

    test('nobody can sit at a table that already has guests', function () {
        $table = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 2);

        expect(fn () => $table->seat(3))
            ->toThrow(FrontOfHouseException::class, 'This table already has guests.');
    });

    test('seating nobody is refused', function () {
        $table = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 0);

        expect(fn () => $table->seat(0))
            ->toThrow(FrontOfHouseException::class, 'Seat at least one guest.');
    });

    test('freeing a table clears the guests', function () {
        $table = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 2);

        $table->free();

        expect($table->hasGuests())->toBeFalse()
            ->and($table->status())->toBe('free');
    });

    test('freeing an empty table is a no-op', function () {
        $table = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 0);

        $table->free();

        expect($table->guestCount())->toBe(0);
    });
});
