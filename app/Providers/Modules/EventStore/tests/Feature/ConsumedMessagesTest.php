<?php

use App\Providers\BoundedContexts\Billing\Infrastructure\Processors\RecordServedDishes;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\KitchenDishReady;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Projection\ToServeProjector;
use App\Providers\Modules\EventStore\Application\ConsumedMessages;
use App\Providers\Modules\EventStore\Domain\StoredEvent;
use App\Providers\Modules\SharedKernel\Domain\EventType;
use App\Providers\Modules\SharedKernel\Domain\StreamId;

/**
 * @param  array<string, mixed>  $payload
 */
function aFactInTheLog(string $type, array $payload, ?int $contractVersion = 1): StoredEvent
{
    return new StoredEvent(1, new StreamId('integration-test'), 1, new EventType($type), $payload, '2026-10-22 12:00:00', contractVersion: $contractVersion);
}

function aDishReadyPayload(): array
{
    return ['tableOrderId' => 'order-1', 'lineId' => 'order-1-1', 'dishName' => 'Green salad', 'tableId' => 'table-4', 'tableLabel' => 'Window 4', 'quantity' => 2];
}

describe('what a consumer reads of the log', function () {
    test('is the message its own context declared for that contract, typed in its own words', function () {
        $message = app(ConsumedMessages::class)->read(aFactInTheLog('kitchen.dish_ready', aDishReadyPayload()), ToServeProjector::class);

        expect($message)->toBeInstanceOf(KitchenDishReady::class)
            ->and((string) $message->ticketId)->toBe('order-1')
            ->and((string) $message->tableId)->toBe('table-4')
            ->and($message->quantity)->toBe(2);
    });

    test('is nothing when the context of the consumer does not read that contract', function () {
        expect(app(ConsumedMessages::class)->read(aFactInTheLog('kitchen.dish_ready', aDishReadyPayload()), RecordServedDishes::class))->toBeNull();
    });

    test('is nothing for a fact nobody declared', function () {
        expect(app(ConsumedMessages::class)->read(aFactInTheLog('kitchen.ticket_completed', []), ToServeProjector::class))->toBeNull();
    });

    test('is nothing for a version of the contract the consumer does not read', function () {
        expect(app(ConsumedMessages::class)->read(aFactInTheLog('kitchen.dish_ready', aDishReadyPayload(), 2), ToServeProjector::class))->toBeNull();
    });
});
