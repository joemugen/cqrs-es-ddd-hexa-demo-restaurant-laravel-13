<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;

describe('the id of a visit', function () {
    test('comes from the table and from how many visits it has seated, whatever the client sent', function () {
        expect(VisitId::forSeating(new TableId('table-4'), 3))->toEqual(VisitId::forSeating(new TableId('table-4'), 3));
    });

    test('is another one for the next seating of the table, and for another table', function () {
        $visit = VisitId::forSeating(new TableId('table-4'), 3);

        expect($visit)->not->toEqual(VisitId::forSeating(new TableId('table-4'), 4))
            ->and($visit)->not->toEqual(VisitId::forSeating(new TableId('table-7'), 3));
    });

    test('is a UUID, which is neither the table nor an order', function () {
        expect((string) VisitId::forSeating(new TableId('table-4'), 1))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
    });
});
