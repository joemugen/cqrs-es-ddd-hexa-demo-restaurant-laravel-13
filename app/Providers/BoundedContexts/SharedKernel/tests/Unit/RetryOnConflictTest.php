<?php

use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandResponse;
use App\Providers\BoundedContexts\SharedKernel\Domain\ConcurrencyConflict;
use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\Middleware\RetryOnConflict;

final readonly class Reserve implements Command {}

final readonly class Reserved implements CommandResponse
{
    public function jsonSerialize(): array
    {
        return ['accepted' => true];
    }
}

/**
 * @return Closure(Command): ?CommandResponse a handler that conflicts $failures times, then succeeds
 */
function conflictingHandler(int $failures, int &$calls): Closure
{
    return function (Command $command) use ($failures, &$calls): ?CommandResponse {
        $calls++;

        if ($calls <= $failures) {
            throw new ConcurrencyConflict('Stream kitchen-ticket-1 is at version 3, expected 2.');
        }

        return new Reserved;
    };
}

describe('retrying on a version conflict', function (): void {
    it('runs the command once when nothing conflicts', function (): void {
        $calls = 0;

        $response = (new RetryOnConflict(3))->handle(new Reserve, conflictingHandler(0, $calls));

        expect($response)->toBeInstanceOf(Reserved::class)
            ->and($calls)->toBe(1);
    });

    it('replays the command after a conflict', function (): void {
        $calls = 0;

        $response = (new RetryOnConflict(3))->handle(new Reserve, conflictingHandler(2, $calls));

        expect($response)->toBeInstanceOf(Reserved::class)
            ->and($calls)->toBe(3);
    });

    it('gives up after the last attempt and lets the 409 through', function (): void {
        $calls = 0;
        $handler = conflictingHandler(5, $calls);

        expect(fn () => (new RetryOnConflict(3))->handle(new Reserve, $handler))
            ->toThrow(ConcurrencyConflict::class);
        expect($calls)->toBe(3);
    });

    it('does not retry a refusal that is not a conflict', function (): void {
        $calls = 0;
        $refusing = function () use (&$calls): never {
            $calls++;

            throw new RuntimeException('boom');
        };

        expect(fn () => (new RetryOnConflict(3))->handle(new Reserve, $refusing))->toThrow(RuntimeException::class);
        expect($calls)->toBe(1);
    });

    it('renders as a 409', function (): void {
        expect((new ConcurrencyConflict('changed'))->status())->toBe(409);
    });
});
