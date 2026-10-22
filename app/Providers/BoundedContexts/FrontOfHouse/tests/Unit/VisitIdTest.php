<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;

describe('the id of a visit', function () {
    test('is the same for the same table and the same key, so seating the same guests twice finds the first visit', function () {
        expect(VisitId::derivedFrom(new TableId('table-4'), 'key-1'))->toEqual(VisitId::derivedFrom(new TableId('table-4'), 'key-1'));
    });

    test('is another one for another key, and for another table', function () {
        $visit = VisitId::derivedFrom(new TableId('table-4'), 'key-1');

        expect($visit)->not->toEqual(VisitId::derivedFrom(new TableId('table-4'), 'key-2'))
            ->and($visit)->not->toEqual(VisitId::derivedFrom(new TableId('table-7'), 'key-1'));
    });

    test('is a UUID, which is neither the table nor an order', function () {
        expect((string) VisitId::derivedFrom(new TableId('table-4'), 'key-1'))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
    });
});
