<?php

use App\Providers\Modules\SharedKernel\Domain\RecordsEvents;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;

function somethingHappened(): TypedDomainEvent
{
    return new class implements TypedDomainEvent {};
}

function anAggregateThatRecords(): object
{
    return new class
    {
        use RecordsEvents;

        public function doSomething(TypedDomainEvent $fact): void
        {
            $this->recordThat($fact);
        }
    };
}

describe('an aggregate that records events', function () {
    test('keeps the typed facts of what it decided until they are released', function () {
        $aggregate = anAggregateThatRecords();
        $fact = somethingHappened();

        $aggregate->doSomething($fact);

        expect($aggregate->releaseEvents())->toBe([$fact]);
    });

    test('releases a fact once: a second release is empty', function () {
        $aggregate = anAggregateThatRecords();
        $aggregate->doSomething(somethingHappened());
        $aggregate->releaseEvents();

        expect($aggregate->releaseEvents())->toBe([]);
    });

    test('has nothing to release before anything happened', function () {
        expect(anAggregateThatRecords()->releaseEvents())->toBe([]);
    });
});
