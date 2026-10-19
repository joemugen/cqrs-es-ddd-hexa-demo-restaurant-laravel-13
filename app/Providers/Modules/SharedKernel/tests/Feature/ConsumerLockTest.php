<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Processors\OpenKitchenTicketProcessor;
use App\Providers\Modules\SharedKernel\Application\EventConsumer;
use App\Providers\Modules\SharedKernel\Infrastructure\Eventing\ExclusiveConsumer;
use Illuminate\Support\Facades\Cache;

final class CountingConsumer implements EventConsumer
{
    public int $calls = 0;

    public function __construct(private int $handles = 1, private ?Closure $during = null, private bool $fails = false) {}

    public function consume(): int
    {
        $this->calls++;

        if ($this->during !== null) {
            ($this->during)();
        }

        if ($this->fails) {
            throw new RuntimeException('failed while holding the lock');
        }

        return $this->handles;
    }
}

describe('consumer locks', function (): void {
    describe('around one consumer', function (): void {
        it('runs the consumer and returns what it handled when nobody else holds it', function (): void {
            $inner = new CountingConsumer(3);

            expect((new ExclusiveConsumer($inner, 30))->consume())->toBe(3)
                ->and($inner->calls)->toBe(1);
        });

        it('skips the consumer while another process holds its lock', function (): void {
            $inner = new CountingConsumer(3);
            $held = Cache::lock('event-consumer:'.$inner::class, 30);
            $held->get();

            expect((new ExclusiveConsumer($inner, 30))->consume())->toBe(0)
                ->and($inner->calls)->toBe(0);

            $held->release();
        });

        it('lets the next call through once the first one is done', function (): void {
            $inner = new CountingConsumer(1);
            $consumer = new ExclusiveConsumer($inner, 30);

            $consumer->consume();
            $consumer->consume();

            expect($inner->calls)->toBe(2);
        });

        it('releases the lock even when the consumer fails', function (): void {
            $inner = new CountingConsumer(1, fails: true);

            expect(fn () => (new ExclusiveConsumer($inner, 30))->consume())->toThrow(RuntimeException::class);

            $lock = Cache::lock('event-consumer:'.$inner::class, 30);
            expect($lock->get())->toBeTrue();
            $lock->release();
        });

        it('keeps two runs of the same consumer from overlapping', function (): void {
            $overlapped = null;
            $inner = new CountingConsumer(1, function () use (&$overlapped, &$consumer): void {
                $overlapped = $consumer->consume();
            });
            $consumer = new ExclusiveConsumer($inner, 30);

            $consumer->consume();

            expect($overlapped)->toBe(0)
                ->and($inner->calls)->toBe(1);
        });
    });

    describe('around the events of a context', function (): void {
        beforeEach(function (): void {
            $this->seed(FrontOfHouseSeeder::class);
            $this->seed(KitchenSeeder::class);
            $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        });

        it('does not let a request and a worker consume the same events at once', function (): void {
            $lock = Cache::lock('event-consumer:'.OpenKitchenTicketProcessor::class, 30);
            $lock->get();

            $this->postJson('/api/front-of-house/tables/table-4/orders', [
                'lines' => [['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter']],
            ])->assertOk();

            expect($this->getJson('/api/kitchen/screen')->json())->toBe([]);

            $lock->release();
            $this->artisan('kitchen:consume', ['--stop-when-empty' => true])->assertSuccessful();

            expect($this->getJson('/api/kitchen/screen')->json())->toHaveCount(1);
        });
    });

    describe('around a worker', function (): void {
        it('refuses a second worker for the same context', function (): void {
            $lock = Cache::lock('event-worker:kitchen', 60);
            $lock->get();

            $this->artisan('kitchen:consume', ['--stop-when-empty' => true])
                ->expectsOutputToContain('Another kitchen worker is already running')
                ->assertFailed();

            $lock->release();
        });

        it('does not stop a worker of another context', function (): void {
            $lock = Cache::lock('event-worker:kitchen', 60);
            $lock->get();

            $this->artisan('billing:consume', ['--stop-when-empty' => true])->assertSuccessful();

            $lock->release();
        });

        it('gives the context back when it finishes', function (): void {
            $this->artisan('kitchen:consume', ['--stop-when-empty' => true])->assertSuccessful();

            $lock = Cache::lock('event-worker:kitchen', 60);
            expect($lock->get())->toBeTrue();
            $lock->release();
        });
    });
});
