<?php

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenException;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicket;
use App\Providers\BoundedContexts\SharedKernel\Domain\DomainEvent;

function openTicket(): KitchenTicket
{
    return KitchenTicket::open('ticket-1', 'table-4', 'Window 4', [
        ['lineId' => 'salad', 'dishName' => 'Green salad', 'station' => 'garnish', 'cookingMinutes' => 4, 'quantity' => 1, 'course' => 'starter', 'allergens' => []],
        ['lineId' => 'steak', 'dishName' => 'Steak frites', 'station' => 'grill', 'cookingMinutes' => 12, 'quantity' => 1, 'course' => 'main', 'allergens' => ['gluten']],
    ]);
}

test('a ticket is done only when every dish is ready', function () {
    $ticket = openTicket();
    $ticket->fireCourse('starter');
    $ticket->markReady('salad');

    expect($ticket->isComplete())->toBeFalse();

    $ticket->fireCourse('main');
    $ticket->markReady('steak');

    expect($ticket->isComplete())->toBeTrue()
        ->and(array_map(fn (DomainEvent $event): string => (string) $event->type, iterator_to_array($ticket->pendingEvents())))->toBe([
            'kitchen.ticket_opened',
            'kitchen.dish_fired',
            'kitchen.dish_ready',
            'kitchen.dish_fired',
            'kitchen.dish_ready',
            'kitchen.ticket_completed',
        ]);
});

test('a dish cannot be ready before it is fired', function () {
    $ticket = openTicket();

    expect(fn () => $ticket->markReady('salad'))
        ->toThrow(KitchenException::class, 'Fire the dish before it can be ready.');
});

test('replaying the stream rebuilds the same decision', function () {
    $original = openTicket();
    $original->fireCourse('starter');
    $original->markReady('salad');

    $rebuilt = KitchenTicket::fromHistory('ticket-1', $original->pendingEvents());

    expect($rebuilt->isComplete())->toBeFalse()
        ->and($rebuilt->expectedVersion())->toBe(3)
        ->and($rebuilt->pendingEvents())->toHaveCount(0);
});
