<?php

use App\Providers\BoundedContexts\SharedKernel\Domain\DomainEvent;
use App\Providers\BoundedContexts\SharedKernel\Domain\DomainEvents;
use App\Providers\BoundedContexts\SharedKernel\Domain\EventType;
use App\Providers\BoundedContexts\SharedKernel\Domain\StreamId;

describe('a typed string', function (): void {
    it('casts to a string and serializes to JSON', function (): void {
        $stream = new StreamId('kitchen-ticket-1');

        expect((string) $stream)->toBe('kitchen-ticket-1')
            ->and(json_encode(['stream' => $stream]))->toBe('{"stream":"kitchen-ticket-1"}');
    });

    it('is compared by type and by value', function (): void {
        expect((new StreamId('a'))->equals(new StreamId('a')))->toBeTrue()
            ->and((new StreamId('a'))->equals(new StreamId('b')))->toBeFalse()
            ->and((new StreamId('a'))->equals(new EventType('a')))->toBeFalse();
    });
});

describe('an event type', function (): void {
    it('is recognised by its name', function (): void {
        expect((new EventType('kitchen.dish_ready'))->is('kitchen.dish_ready'))->toBeTrue()
            ->and((new EventType('kitchen.dish_ready'))->is('kitchen.dish_fired'))->toBeFalse();
    });
});

describe('domain events', function (): void {
    it('are collected without changing the collection they started from', function (): void {
        $none = new DomainEvents;
        $one = $none->with(DomainEvent::now('tests.opened', ['id' => '1']));

        expect($none)->toHaveCount(0)
            ->and($none->isEmpty())->toBeTrue()
            ->and($one)->toHaveCount(1)
            ->and(iterator_to_array($one)[0]->type->is('tests.opened'))->toBeTrue();
    });
});
