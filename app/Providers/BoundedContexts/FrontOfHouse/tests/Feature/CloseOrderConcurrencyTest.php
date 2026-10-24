<?php

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\CloseOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FulfilmentStatus;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\OrderFulfilment;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\Modules\SharedKernel\Domain\ConcurrencyConflict;

/**
 * Wraps the real repository and lets somebody else save the order the first time it is read: the closing then decides on a state
 * that is no longer the current one.
 */
function aRepositoryWhereSomebodyElseSavesFirst(OrderFulfilmentRepository $real, callable $somebodyElse): OrderFulfilmentRepository
{
    return new class($real, $somebodyElse) implements OrderFulfilmentRepository
    {
        private bool $interfered = false;

        public function __construct(private readonly OrderFulfilmentRepository $real, private $somebodyElse) {}

        public function exists(TableOrderId $id): bool
        {
            return $this->real->exists($id);
        }

        public function get(TableOrderId $id): OrderFulfilment
        {
            $read = $this->real->get($id);

            if (! $this->interfered) {
                $this->interfered = true;
                ($this->somebodyElse)($id);
            }

            return $read;
        }

        public function openOf(VisitId $visit): array
        {
            return $this->real->openOf($visit);
        }

        public function save(OrderFulfilment $fulfilment): void
        {
            $this->real->save($fulfilment);
        }
    };
}

describe('the fulfilment of an order, written by the room and by the processors', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->id = new TableOrderId($this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter']]])->json('tableOrderId'));
        $this->fulfilments = app(OrderFulfilmentRepository::class);
    });

    test('is not saved from a state somebody else has changed since it was read', function () {
        $first = $this->fulfilments->get($this->id);
        $second = $this->fulfilments->get($this->id);
        $first->close();
        $this->fulfilments->save($first);

        $second->close();

        expect(fn () => $this->fulfilments->save($second))->toThrow(ConcurrencyConflict::class);
    });

    test('is saved again from the state that was read after the other save', function () {
        $first = $this->fulfilments->get($this->id);
        $first->confirm();
        $this->fulfilments->save($first);

        $second = $this->fulfilments->get($this->id);
        $second->close();
        $this->fulfilments->save($second);

        expect($this->fulfilments->get($this->id)->status())->toBe(FulfilmentStatus::CLOSED);
    });

    test('is decided again by the processor when it lost the race: the command reads it again and closes it', function () {
        $this->app->instance(OrderFulfilmentRepository::class, aRepositoryWhereSomebodyElseSavesFirst(
            $this->fulfilments,
            function (TableOrderId $id): void {
                $other = $this->fulfilments->get($id);
                $other->confirm();
                $this->fulfilments->save($other);
            },
        ));

        app(FrontOfHouseCommandBus::class)->dispatch(new CloseOrder($this->id));

        expect($this->fulfilments->get($this->id)->status())->toBe(FulfilmentStatus::CLOSED);
    });
});
