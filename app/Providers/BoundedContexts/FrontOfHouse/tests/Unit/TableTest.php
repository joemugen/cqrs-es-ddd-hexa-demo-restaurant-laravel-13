<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\BillRequested;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Table;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;

function aFreeTable(): Table
{
    return Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 0);
}

function anOccupiedTable(string $visit = 'visit-1'): Table
{
    return Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 2, new VisitId($visit));
}

describe('a table', function () {
    describe('when guests are seated', function () {
        test('starts a visit, which is the guests of the table until they leave', function () {
            $table = aFreeTable();

            $table->seat(new VisitId('visit-1'), 2);

            expect($table->hasGuests())->toBeTrue()
                ->and($table->visit())->toEqual(new VisitId('visit-1'))
                ->and($table->isHosting(new VisitId('visit-1')))->toBeTrue()
                ->and($table->isHosting(new VisitId('visit-2')))->toBeFalse();
        });

        test('cannot seat guests where there are some already', function () {
            expect(fn () => anOccupiedTable()->seat(new VisitId('visit-2'), 2))
                ->toThrow(FrontOfHouseException::class, 'This table already has guests.');
        });

        test('seats at least one guest', function () {
            expect(fn () => aFreeTable()->seat(new VisitId('visit-1'), 0))
                ->toThrow(FrontOfHouseException::class, 'Seat at least one guest.');
        });
    });

    describe('when the bill is requested', function () {
        test('records that the guests of the visit asked for it', function () {
            $table = anOccupiedTable();

            $table->requestBill();

            expect($table->releaseEvents())->toEqual([new BillRequested(new TableId('table-4'), new VisitId('visit-1'))]);
        });

        test('has no bill to request without guests', function () {
            $table = aFreeTable();

            expect(fn () => $table->requestBill())->toThrow(FrontOfHouseException::class, 'This table has no guests to bill.')
                ->and($table->releaseEvents())->toBe([]);
        });
    });

    describe('when the guests of a visit are gone', function () {
        test('is free again', function () {
            $table = anOccupiedTable();

            $table->free(new VisitId('visit-1'));

            expect($table->hasGuests())->toBeFalse()
                ->and($table->visit())->toBeNull();
        });

        test('stays with the guests who sit there now when it is told that the previous ones paid', function () {
            $table = anOccupiedTable('visit-2');

            $table->free(new VisitId('visit-1'));

            expect($table->hasGuests())->toBeTrue()
                ->and($table->isHosting(new VisitId('visit-2')))->toBeTrue();
        });
    });
});
