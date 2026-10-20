<?php

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\ConfirmOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\PlaceTableOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Application\Command\OpenKitchenTicket;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Domain\StoredEvent;
use App\Providers\Modules\SharedKernel\Infrastructure\Causation;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;

/**
 * @return StoredEvent the only event of this type in the log
 */
function theEventOfType(string $type): StoredEvent
{
    $events = array_values(array_filter(
        app(EventStore::class)->readAllFrom(0),
        fn (StoredEvent $event): bool => $event->type->is($type),
    ));

    expect($events)->toHaveCount(1);

    return $events[0];
}

/**
 * @param  array<int, array<string, mixed>>  $logged
 * @return array<string, mixed> the log context of the only command of this class that was handled
 */
function theHandledCommand(array $logged, string $commandClass): array
{
    $handled = array_values(array_filter(
        $logged,
        fn (array $context): bool => $context['command'] === $commandClass,
    ));

    expect($handled)->toHaveCount(1);

    return $handled[0];
}

describe('the causation id', function (): void {
    describe('on the chain of an order', function (): void {
        beforeEach(function (): void {
            $this->logged = [];
            Event::listen(MessageLogged::class, function (MessageLogged $log): void {
                if ($log->message === 'Command handled') {
                    $this->logged[] = $log->context;
                }
            });
            $this->seed(FrontOfHouseSeeder::class);
            $this->seed(KitchenSeeder::class);
            $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
            $this->logged = [];
            $this->order = ['lines' => [['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter']]];
        });

        it('points each link to the message that caused it, from the order to the confirmation', function (string $mode): void {
            config(['consumption.mode' => $mode]);
            $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order)->assertOk();
            Context::forget('causation_id');

            if ($mode === 'worker') {
                foreach (['front-of-house', 'kitchen', 'front-of-house'] as $context) {
                    $this->artisan("{$context}:consume", ['--stop-when-empty' => true])->assertSuccessful();
                }
            }

            $placeOrder = theHandledCommand($this->logged, PlaceTableOrder::class);
            $openTicket = theHandledCommand($this->logged, OpenKitchenTicket::class);
            $confirm = theHandledCommand($this->logged, ConfirmOrder::class);
            $placed = theEventOfType('front_of_house.table_order_placed');
            $opened = theEventOfType('kitchen.ticket_opened');

            expect($placeOrder['caused_by'])->toBeNull()
                ->and($placed->causationId)->toBe($placeOrder['command_id'])
                ->and($openTicket['caused_by'])->toBe((string) $placed->position)
                ->and($opened->causationId)->toBe($openTicket['command_id'])
                ->and($confirm['caused_by'])->toBe((string) $opened->position);
        })->with(['in the request' => 'sync', 'across the workers' => 'worker']);

        it('gives every command its own id', function (): void {
            $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order)->assertOk();

            $ids = array_column($this->logged, 'command_id');

            expect($ids)->not->toBeEmpty()->and(array_unique($ids))->toHaveCount(count($ids));
        });

        it('is read back on the event with the position of its cause', function (): void {
            $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order)->assertOk();

            expect(theEventOfType('kitchen.ticket_opened')->causationId)->toBeString()->not->toBeEmpty();
        });
    });

    describe('around a command', function (): void {
        it('is the same after a command ran as it was before', function (): void {
            Causation::resume('42');

            Causation::within(function (): void {
                Causation::begin();
            });

            expect(Causation::current())->toBe('42');
        });

        it('is gone again when there was none before', function (): void {
            Context::forget('causation_id');

            Causation::within(function (): void {
                Causation::resume('7');
            });

            expect(Causation::current())->toBeNull();
        });
    });
});
