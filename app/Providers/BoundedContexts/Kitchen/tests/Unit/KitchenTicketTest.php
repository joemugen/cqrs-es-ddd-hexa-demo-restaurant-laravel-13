<?php

use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenException;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicket;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\TableLabel;
use App\Providers\BoundedContexts\Kitchen\Domain\TicketLine;
use App\Providers\Modules\SharedKernel\Domain\DomainEvent;

function openTicket(): KitchenTicket
{
    return KitchenTicket::open(new KitchenTicketId('ticket-1'), new PartyRef('table-4'), new TableLabel('Window 4'), [
        new TicketLine(new LineId('salad'), new DishName('Green salad'), 'garnish', 4, 1, 'starter', []),
        new TicketLine(new LineId('steak'), new DishName('Steak frites'), 'grill', 12, 1, 'main', ['gluten']),
    ]);
}

describe('KitchenTicket', function () {
    test('a ticket is done only when every dish is ready', function () {
        $ticket = openTicket();
        $ticket->fireCourse('starter');
        $ticket->markReady(new LineId('salad'));

        expect($ticket->isComplete())->toBeFalse();

        $ticket->fireCourse('main');
        $ticket->markReady(new LineId('steak'));

        expect($ticket->isComplete())->toBeTrue()
            ->and(array_map(fn (DomainEvent $event): string => (string) $event->type, iterator_to_array($ticket->pendingEvents())))->toBe([
                'ticket.opened',
                'ticket.dish_fired',
                'ticket.dish_ready',
                'ticket.dish_fired',
                'ticket.dish_ready',
                'ticket.completed',
            ]);
    });

    test('a dish cannot be ready before it is fired', function () {
        $ticket = openTicket();

        expect(fn () => $ticket->markReady(new LineId('salad')))
            ->toThrow(KitchenException::class, 'Fire the dish before it can be ready.');
    });

    test('replaying the stream rebuilds the same decision', function () {
        $original = openTicket();
        $original->fireCourse('starter');
        $original->markReady(new LineId('salad'));

        $rebuilt = KitchenTicket::fromHistory(new KitchenTicketId('ticket-1'), $original->pendingEvents());

        expect($rebuilt->isComplete())->toBeFalse()
            ->and($rebuilt->expectedVersion())->toBe(3)
            ->and($rebuilt->pendingEvents())->toHaveCount(0);
    });

    describe('when dishes are added after it opened', function () {
        function replacementDishes(): array
        {
            return [new TicketLine(new LineId('fish'), new DishName('Sea bass'), 'grill', 10, 1, 'main', ['fish'])];
        }

        test('the new dishes wait to be fired like the others', function () {
            $ticket = openTicket();

            $ticket->addDishes(replacementDishes());
            $ticket->fireCourse('main');

            expect(array_map(fn (DomainEvent $event): string => (string) $event->type, iterator_to_array($ticket->pendingEvents())))->toBe([
                'ticket.opened',
                'ticket.dishes_added',
                'ticket.dish_fired',
                'ticket.dish_fired',
            ]);
        });

        test('a dish already on the ticket is not added twice', function () {
            $ticket = openTicket();
            $ticket->addDishes(replacementDishes());
            $ticket->addDishes(replacementDishes());

            expect($ticket->pendingEvents())->toHaveCount(2);
        });

        test('a finished ticket is reopened by the new dishes', function () {
            $ticket = openTicket();
            $ticket->fireCourse('starter');
            $ticket->markReady(new LineId('salad'));
            $ticket->fireCourse('main');
            $ticket->markReady(new LineId('steak'));

            $ticket->addDishes(replacementDishes());

            expect($ticket->isComplete())->toBeFalse();
        });

        test('replaying the stream finds the added dishes again', function () {
            $original = openTicket();
            $original->addDishes(replacementDishes());

            $rebuilt = KitchenTicket::fromHistory(new KitchenTicketId('ticket-1'), $original->pendingEvents());
            $rebuilt->fireCourse('main');

            expect($rebuilt->pendingEvents())->toHaveCount(2);
        });

        test('needs at least one dish', function () {
            expect(fn () => openTicket()->addDishes([]))
                ->toThrow(KitchenException::class, 'Add at least one dish to the ticket.');
        });
    });
});
