<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\BillRequested;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Table;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;

describe('a table', function () {
    test('records that the bill was requested', function () {
        $table = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 2);

        $table->requestBill();

        expect($table->releaseEvents())->toEqual([new BillRequested(new TableId('table-4'))]);
    });

    test('has no bill to request without guests', function () {
        $table = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 0);

        expect(fn () => $table->requestBill())->toThrow(FrontOfHouseException::class, 'This table has no guests to bill.')
            ->and($table->releaseEvents())->toBe([]);
    });
});
