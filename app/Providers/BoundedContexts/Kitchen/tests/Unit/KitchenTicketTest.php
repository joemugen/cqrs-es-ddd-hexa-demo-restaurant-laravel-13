<?php

use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishesAdded;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishFired;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishReady;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\TicketCancelled;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\TicketCompleted;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\TicketOpened;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenException;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicket;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\TableLabel;
use App\Providers\BoundedContexts\Kitchen\Domain\TicketLine;
use App\Providers\Modules\SharedKernel\Domain\RecordedEvent;
use App\Providers\Modules\SharedKernel\Domain\RecordedEvents;
use App\Providers\Modules\SharedKernel\Domain\StreamedDomainEvent;

/**
 * @return list<TicketLine>
 */
function theDishesOfTheTicket(): array
{
    return [
        new TicketLine(new LineId('salad'), new DishName('Green salad'), 'garnish', 4, 1, 'starter', []),
        new TicketLine(new LineId('steak'), new DishName('Steak frites'), 'grill', 12, 1, 'main', ['gluten']),
    ];
}

/**
 * @return list<TicketLine>
 */
function replacementDishes(): array
{
    return [new TicketLine(new LineId('fish'), new DishName('Sea bass'), 'grill', 10, 1, 'main', ['fish'])];
}

function theTicketWasOpened(): TicketOpened
{
    return new TicketOpened(new KitchenTicketId('ticket-1'), new PartyRef('table-4'), new TableLabel('Window 4'), theDishesOfTheTicket());
}

function theDishWasFired(string $line, string $course): DishFired
{
    return new DishFired(new KitchenTicketId('ticket-1'), new LineId($line), $course);
}

function theDishWasReady(string $line, string $dish, int $quantity = 1): DishReady
{
    return new DishReady(new KitchenTicketId('ticket-1'), new LineId($line), new DishName($dish), new PartyRef('table-4'), new TableLabel('Window 4'), $quantity);
}

function theTicketWasCompleted(): TicketCompleted
{
    return new TicketCompleted(new KitchenTicketId('ticket-1'));
}

function givenTheTicket(StreamedDomainEvent ...$history): KitchenTicket
{
    return KitchenTicket::fromHistory(
        new KitchenTicketId('ticket-1'),
        new RecordedEvents(array_map(fn (StreamedDomainEvent $fact): RecordedEvent => RecordedEvent::recording($fact), array_values($history))),
    );
}

function thenTheTicketRecorded(KitchenTicket $ticket, StreamedDomainEvent ...$expected): void
{
    expect(array_map(fn (RecordedEvent $event): ?object => $event->fact, iterator_to_array($ticket->pendingEvents())))->toEqual(array_values($expected));
}

describe('KitchenTicket', function () {
    describe('when the guests are gone', function () {
        test('is cancelled: what is still to cook is not cooked', function () {
            $ticket = givenTheTicket(theTicketWasOpened(), theDishWasFired('salad', 'starter'));

            $ticket->cancel();

            thenTheTicketRecorded($ticket, new TicketCancelled(new KitchenTicketId('ticket-1')));
            expect($ticket->isCancelled())->toBeTrue();
        });

        test('cannot be fired or made ready any more', function () {
            $ticket = givenTheTicket(theTicketWasOpened(), theDishWasFired('salad', 'starter'), new TicketCancelled(new KitchenTicketId('ticket-1')));

            expect(fn () => $ticket->fireCourse('main'))->toThrow(KitchenException::class, 'A cancelled ticket cannot change.')
                ->and(fn () => $ticket->markReady(new LineId('salad')))->toThrow(KitchenException::class, 'A cancelled ticket cannot change.');
        });

        test('is cancelled once, and not at all when everything was cooked', function () {
            $cancelled = givenTheTicket(theTicketWasOpened(), new TicketCancelled(new KitchenTicketId('ticket-1')));
            $done = givenTheTicket(
                theTicketWasOpened(),
                theDishWasFired('salad', 'starter'), theDishWasReady('salad', 'Green salad'),
                theDishWasFired('steak', 'main'), theDishWasReady('steak', 'Steak frites'),
                theTicketWasCompleted(),
            );

            $cancelled->cancel();
            $done->cancel();

            expect($cancelled->pendingEvents()->isEmpty())->toBeTrue()
                ->and($done->pendingEvents()->isEmpty())->toBeTrue();
        });
    });

    describe('when it opens', function () {
        test('records that it opened, with the dishes and the party', function () {
            $ticket = KitchenTicket::open(new KitchenTicketId('ticket-1'), new PartyRef('table-4'), new TableLabel('Window 4'), theDishesOfTheTicket());

            thenTheTicketRecorded($ticket, theTicketWasOpened());
        });

        test('needs at least one dish', function () {
            expect(fn () => KitchenTicket::open(new KitchenTicketId('ticket-1'), new PartyRef('table-4'), new TableLabel('Window 4'), []))
                ->toThrow(KitchenException::class, 'A ticket needs at least one dish.');
        });
    });

    describe('when a course is fired', function () {
        test('fires each dish of the course that waits', function () {
            $ticket = givenTheTicket(theTicketWasOpened());

            $ticket->fireCourse('starter');

            thenTheTicketRecorded($ticket, theDishWasFired('salad', 'starter'));
        });

        test('does not fire a dish twice', function () {
            $ticket = givenTheTicket(theTicketWasOpened(), theDishWasFired('salad', 'starter'));

            expect(fn () => $ticket->fireCourse('starter'))->toThrow(KitchenException::class, 'No starter is waiting on this ticket.');
        });

        test('has nothing to fire for a course nobody ordered', function () {
            expect(fn () => givenTheTicket(theTicketWasOpened())->fireCourse('dessert'))
                ->toThrow(KitchenException::class, 'No dessert is waiting on this ticket.');
        });
    });

    describe('when a dish is ready', function () {
        test('records it, and the ticket goes on while other dishes wait', function () {
            $ticket = givenTheTicket(theTicketWasOpened(), theDishWasFired('salad', 'starter'));

            $ticket->markReady(new LineId('salad'));

            thenTheTicketRecorded($ticket, theDishWasReady('salad', 'Green salad'));
            expect($ticket->isComplete())->toBeFalse();
        });

        test('records that the ticket is completed with the last dish', function () {
            $ticket = givenTheTicket(
                theTicketWasOpened(),
                theDishWasFired('salad', 'starter'),
                theDishWasReady('salad', 'Green salad'),
                theDishWasFired('steak', 'main'),
            );

            $ticket->markReady(new LineId('steak'));

            thenTheTicketRecorded($ticket, theDishWasReady('steak', 'Steak frites'), theTicketWasCompleted());
            expect($ticket->isComplete())->toBeTrue();
        });

        test('cannot be ready before it is fired', function () {
            $ticket = givenTheTicket(theTicketWasOpened());

            expect(fn () => $ticket->markReady(new LineId('salad')))->toThrow(KitchenException::class, 'Fire the dish before it can be ready.');
        });

        test('cannot be ready twice', function () {
            $ticket = givenTheTicket(theTicketWasOpened(), theDishWasFired('salad', 'starter'), theDishWasReady('salad', 'Green salad'));

            expect(fn () => $ticket->markReady(new LineId('salad')))->toThrow(KitchenException::class, 'That dish is already ready.');
        });

        test('is not on a ticket that does not have it', function () {
            expect(fn () => givenTheTicket(theTicketWasOpened())->markReady(new LineId('fish')))
                ->toThrow(KitchenException::class, 'That dish is not on this ticket.');
        });
    });

    describe('when it is completed', function () {
        function aCompletedTicket(): KitchenTicket
        {
            return givenTheTicket(
                theTicketWasOpened(),
                theDishWasFired('salad', 'starter'),
                theDishWasReady('salad', 'Green salad'),
                theDishWasFired('steak', 'main'),
                theDishWasReady('steak', 'Steak frites'),
                theTicketWasCompleted(),
            );
        }

        test('nothing can be fired any more', function () {
            expect(fn () => aCompletedTicket()->fireCourse('main'))->toThrow(KitchenException::class, 'A finished ticket cannot change.');
        });

        test('added dishes make it wait again', function () {
            $ticket = aCompletedTicket();

            $ticket->addDishes(replacementDishes());

            thenTheTicketRecorded($ticket, new DishesAdded(new KitchenTicketId('ticket-1'), replacementDishes()));
            expect($ticket->isComplete())->toBeFalse();
        });
    });

    describe('when dishes are added after it opened', function () {
        test('records them, and they wait to be fired like the others', function () {
            $ticket = givenTheTicket(theTicketWasOpened());

            $ticket->addDishes(replacementDishes());
            $ticket->fireCourse('main');

            thenTheTicketRecorded(
                $ticket,
                new DishesAdded(new KitchenTicketId('ticket-1'), replacementDishes()),
                theDishWasFired('steak', 'main'),
                theDishWasFired('fish', 'main'),
            );
        });

        test('leaves alone a dish that is already on it', function () {
            $ticket = givenTheTicket(theTicketWasOpened(), new DishesAdded(new KitchenTicketId('ticket-1'), replacementDishes()));

            $ticket->addDishes(replacementDishes());

            thenTheTicketRecorded($ticket);
        });

        test('needs at least one dish', function () {
            expect(fn () => givenTheTicket(theTicketWasOpened())->addDishes([]))
                ->toThrow(KitchenException::class, 'Add at least one dish to the ticket.');
        });
    });

    describe('when it is rebuilt from its history', function () {
        test('takes the same decisions and has nothing left to commit', function () {
            $ticket = givenTheTicket(theTicketWasOpened(), theDishWasFired('salad', 'starter'), theDishWasReady('salad', 'Green salad'));

            expect($ticket->isComplete())->toBeFalse()
                ->and($ticket->expectedVersion())->toBe(3)
                ->and($ticket->pendingEvents())->toHaveCount(0);
        });
    });
});
