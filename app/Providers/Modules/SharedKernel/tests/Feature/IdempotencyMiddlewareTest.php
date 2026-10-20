<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\Bus\Exception\IdempotencyKeyReused;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;
use App\Providers\Modules\SharedKernel\Application\IdempotentCommand;
use App\Providers\Modules\SharedKernel\Domain\IdempotencyKey;
use App\Providers\Modules\SharedKernel\Domain\InvalidValue;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\IdempotencyMiddleware;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\TransactionMiddleware;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\MiddlewareCommandBus;
use App\Providers\Modules\SharedKernel\Infrastructure\Tables as SharedKernelTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RingUpTheTill implements IdempotentCommand
{
    public function __construct(public ?IdempotencyKey $key, public string $dish) {}

    public function idempotencyKey(): ?IdempotencyKey
    {
        return $this->key;
    }
}

final readonly class TillRung implements CommandResponse
{
    public function __construct(public string $receiptId) {}

    public function jsonSerialize(): array
    {
        return ['receiptId' => $this->receiptId];
    }
}

#[AsCommandHandler(RingUpTheTill::class)]
final readonly class RingUpTheTillHandler implements CommandHandler
{
    public function __invoke(RingUpTheTill $command): TillRung
    {
        DB::table(Tables::TABLES)->insert(['id' => (string) Str::uuid(), 'label' => 'Rung '.$command->dish, 'guest_count' => 0]);

        if ($command->dish === 'burnt-toast') {
            throw new RuntimeException('The till jammed after writing.');
        }

        return new TillRung((string) Str::uuid());
    }
}

final class IdempotentTestBus extends MiddlewareCommandBus
{
    protected function handlers(): array
    {
        return [RingUpTheTillHandler::class];
    }

    protected function middleware(): array
    {
        return [TransactionMiddleware::class, IdempotencyMiddleware::class];
    }
}

function ringUp(string $dish, ?string $key): ?CommandResponse
{
    return app(IdempotentTestBus::class)->dispatch(new RingUpTheTill($key === null ? null : new IdempotencyKey($key), $dish));
}

function rungUp(string $dish): int
{
    return DB::table(Tables::TABLES)->where('label', 'Rung '.$dish)->count();
}

describe('idempotency middleware', function (): void {
    describe('a key already processed with the same payload', function (): void {
        it('gives back the stored response without running the handler again', function (): void {
            $first = ringUp('soup', 'checkout-1');
            $second = ringUp('soup', 'checkout-1');

            expect($second->jsonSerialize())->toBe($first->jsonSerialize())
                ->and(rungUp('soup'))->toBe(1);
        });
    });

    describe('a key reused with another payload', function (): void {
        it('is refused with a 422 and runs nothing', function (): void {
            ringUp('soup', 'checkout-1');

            try {
                ringUp('steak', 'checkout-1');
                $this->fail('The reused key should have been refused.');
            } catch (IdempotencyKeyReused $refusal) {
                expect($refusal->status())->toBe(422)
                    ->and($refusal->getMessage())->toBe('This Idempotency-Key was already used with another request.');
            }

            expect(rungUp('steak'))->toBe(0);
        });
    });

    describe('two different keys', function (): void {
        it('each run the handler', function (): void {
            $first = ringUp('soup', 'checkout-1');
            $second = ringUp('soup', 'checkout-2');

            expect($second->jsonSerialize())->not->toBe($first->jsonSerialize())
                ->and(rungUp('soup'))->toBe(2);
        });
    });

    describe('a command without a key', function (): void {
        it('runs every time', function (): void {
            ringUp('soup', null);
            ringUp('soup', null);

            expect(rungUp('soup'))->toBe(2)
                ->and(DB::table(SharedKernelTables::IDEMPOTENCY_KEYS)->count())->toBe(0);
        });
    });

    describe('a handler that fails', function (): void {
        it('leaves neither its writes nor the key, so the same key can be tried again', function (): void {
            expect(fn () => ringUp('burnt-toast', 'checkout-1'))->toThrow(RuntimeException::class);

            expect(rungUp('burnt-toast'))->toBe(0)
                ->and(DB::table(SharedKernelTables::IDEMPOTENCY_KEYS)->count())->toBe(0)
                ->and(ringUp('soup', 'checkout-1'))->toBeInstanceOf(TillRung::class);
        });
    });

    describe('concurrent requests with the same key', function (): void {
        it('settle on the first claim: the others replay it', function (): void {
            $responses = array_map(fn (): ?CommandResponse => ringUp('soup', 'checkout-1'), range(1, 5));

            expect(array_unique(array_map(fn (CommandResponse $response): string => json_encode($response), $responses)))->toHaveCount(1)
                ->and(rungUp('soup'))->toBe(1)
                ->and(DB::table(SharedKernelTables::IDEMPOTENCY_KEYS)->count())->toBe(1);
        });
    });

    describe('the key', function (): void {
        it('cannot be blank', function (): void {
            expect(fn () => new IdempotencyKey('  '))->toThrow(InvalidValue::class);
        });

        it('cannot exceed 255 characters', function (): void {
            expect(fn () => new IdempotencyKey(str_repeat('k', 256)))->toThrow(InvalidValue::class);
        });
    });
});
