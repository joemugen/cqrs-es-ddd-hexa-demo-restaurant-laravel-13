<?php

use App\Providers\BoundedContexts\SharedKernel\Domain\DomainEvent;
use App\Providers\BoundedContexts\SharedKernel\Domain\DomainEvents;
use App\Providers\BoundedContexts\SharedKernel\Domain\EventType;
use App\Providers\BoundedContexts\SharedKernel\Domain\StreamId;

it('turns a typed string into a string and into JSON', function (): void {
    $stream = new StreamId('kitchen-ticket-1');

    expect((string) $stream)->toBe('kitchen-ticket-1')
        ->and(json_encode(['stream' => $stream]))->toBe('{"stream":"kitchen-ticket-1"}');
});

it('compares typed strings by type and by value', function (): void {
    expect((new StreamId('a'))->equals(new StreamId('a')))->toBeTrue()
        ->and((new StreamId('a'))->equals(new StreamId('b')))->toBeFalse()
        ->and((new StreamId('a'))->equals(new EventType('a')))->toBeFalse();
});

it('recognises an event type by its name', function (): void {
    expect((new EventType('kitchen.dish_ready'))->is('kitchen.dish_ready'))->toBeTrue()
        ->and((new EventType('kitchen.dish_ready'))->is('kitchen.dish_fired'))->toBeFalse();
});

it('collects domain events without changing the collection it started from', function (): void {
    $none = new DomainEvents;
    $one = $none->with(DomainEvent::now('tests.opened', ['id' => '1']));

    expect($none)->toHaveCount(0)
        ->and($none->isEmpty())->toBeTrue()
        ->and($one)->toHaveCount(1)
        ->and(iterator_to_array($one)[0]->type->is('tests.opened'))->toBeTrue();
});
