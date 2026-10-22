<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\BillRequested;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Table;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;

function aFreeTable(int $visitsSeated = 0): Table
{
    return Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 0, null, $visitsSeated);
}

function anOccupiedTable(string $visit = 'visit-1'): Table
{
    return Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 2, new VisitId($visit));
}

describe('a table', function () {
    describe('when guests are seated', function () {
        test('starts a visit, which is the guests of the table until they leave', function () {
            $table = aFreeTable();

            $visit = $table->seat(2);

            expect($table->hasGuests())->toBeTrue()
                ->and($table->visit())->toEqual($visit)
                ->and($table->isHosting($visit))->toBeTrue()
                ->and($table->isHosting(new VisitId('visit-2')))->toBeFalse();
        });

        test('names the visit after the table and the visits it has seated, so a replay cannot name it twice', function () {
            $table = aFreeTable(visitsSeated: 2);

            expect($table->seat(2))->toEqual(VisitId::forSeating(new TableId('table-4'), 3))
                ->and($table->visitsSeated())->toBe(3);
        });

        test('cannot seat guests where there are some already', function () {
            expect(fn () => anOccupiedTable()->seat(2))
                ->toThrow(FrontOfHouseException::class, 'This table already has guests.');
        });

        test('seats at least one guest', function () {
            expect(fn () => aFreeTable()->seat(0))
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
