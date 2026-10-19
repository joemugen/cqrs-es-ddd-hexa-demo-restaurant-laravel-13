<?php

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\PlaceTableOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\RequestedLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Domain\StoredEvent;
use App\Providers\Modules\SharedKernel\Application\EventConsumer;
use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;
use App\Providers\Modules\SharedKernel\Infrastructure\Eventing\ExclusiveConsumer;
use Illuminate\Support\Facades\Context;

const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

/**
 * @return list<StoredEvent> the events of this type, oldest first
 */
function eventsOfType(string $type): array
{
    return array_values(array_filter(
        app(EventStore::class)->readAllFrom(0),
        fn (StoredEvent $event): bool => $event->type->is($type),
    ));
}

describe('the correlation id', function (): void {
    describe('on a request', function (): void {
        beforeEach(function (): void {
            $this->seed(FrontOfHouseSeeder::class);
        });

        it('is generated and returned when the caller sends none', function (): void {
            $response = $this->getJson('/api/front-of-house/floor-plan')->assertOk();

            expect($response->headers->get('X-Correlation-Id'))->toMatch(UUID_PATTERN);
        });

        it('is the one the caller sent, echoed back', function (): void {
            $this->getJson('/api/front-of-house/floor-plan', ['X-Correlation-Id' => 'checkout-42.a_b'])
                ->assertHeader('X-Correlation-Id', 'checkout-42.a_b');
        });

        it('is read from X-Request-Id when X-Correlation-Id is absent', function (): void {
            $this->getJson('/api/front-of-house/floor-plan', ['X-Request-Id' => 'req-9'])
                ->assertHeader('X-Correlation-Id', 'req-9');
        });

        it('is replaced when the caller sends something unfit for a log line or a header', function (string $value): void {
            $response = $this->getJson('/api/front-of-house/floor-plan', ['X-Correlation-Id' => $value]);

            expect($response->headers->get('X-Correlation-Id'))->toMatch(UUID_PATTERN);
        })->with(['has spaces', str_repeat('a', 65), 'semi;colon', 'new<script>']);

        it('is on a refusal too, so a failure can be traced', function (): void {
            $this->postJson('/api/front-of-house/tables/missing/guests', ['guestCount' => 2], ['X-Correlation-Id' => 'trace-404'])
                ->assertNotFound()
                ->assertHeader('X-Correlation-Id', 'trace-404');
        });
    });

    describe('on the events', function (): void {
        beforeEach(function (): void {
            $this->seed(FrontOfHouseSeeder::class);
            $this->seed(KitchenSeeder::class);
            $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
            $this->order = ['lines' => [['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter']]];
        });

        it('stamps the events a request appends with its id', function (): void {
            $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order, ['X-Correlation-Id' => 'trace-7'])->assertOk();

            expect(eventsOfType('front_of_house.table_order_placed')[0]->correlationId)->toBe('trace-7');
        });

        it('carries it to the events a consumer appends in the same request', function (): void {
            $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order, ['X-Correlation-Id' => 'trace-8'])->assertOk();

            expect(eventsOfType('kitchen.ticket_opened')[0]->correlationId)->toBe('trace-8');
        });

        it('carries it across a worker, which resumes the id of the event it reacts to', function (): void {
            config(['consumption.mode' => 'worker']);
            $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order, ['X-Correlation-Id' => 'trace-9'])->assertOk();
            Context::forget('correlation_id');

            $this->artisan('kitchen:consume', ['--stop-when-empty' => true])->assertSuccessful();

            expect(eventsOfType('kitchen.ticket_opened')[0]->correlationId)->toBe('trace-9');
        });

        it('keeps two requests apart in a worker that handles both', function (): void {
            config(['consumption.mode' => 'worker']);
            $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order, ['X-Correlation-Id' => 'first'])->assertOk();
            $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order, ['X-Correlation-Id' => 'second'])->assertOk();
            Context::forget('correlation_id');

            $this->artisan('kitchen:consume', ['--stop-when-empty' => true])->assertSuccessful();

            expect(array_map(fn (StoredEvent $event) => $event->correlationId, eventsOfType('kitchen.ticket_opened')))
                ->toBe(['first', 'second']);
        });

        it('is generated for a command that starts outside a request', function (): void {
            Context::forget('correlation_id');

            app(FrontOfHouseCommandBus::class)->dispatch(new PlaceTableOrder(
                TableOrderId::derivedFrom(new TableId('table-4'), 'cli'),
                new TableId('table-4'),
                [new RequestedLine(new MenuItemId('green-salad'), 1, 'starter')],
            ));

            expect(eventsOfType('front_of_house.table_order_placed')[0]->correlationId)->toMatch(UUID_PATTERN);
        });
    });

    describe('around a consumer', function (): void {
        it('is the same after the consumer ran as it was before', function (): void {
            Correlation::begin('outer');
            $consumer = new ExclusiveConsumer(new class implements EventConsumer
            {
                public function consume(): int
                {
                    Correlation::resume('inner');

                    return 1;
                }
            }, 30);

            $consumer->consume();

            expect(Correlation::current())->toBe('outer');
        });

        it('is gone again when there was none before', function (): void {
            Context::forget('correlation_id');
            $consumer = new ExclusiveConsumer(new class implements EventConsumer
            {
                public function consume(): int
                {
                    Correlation::resume('inner');

                    return 1;
                }
            }, 30);

            $consumer->consume();

            expect(Correlation::current())->toBeNull();
        });
    });
});
