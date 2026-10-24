<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware;

use App\Providers\Modules\SharedKernel\Application\Bus\CommandMiddleware;
use App\Providers\Modules\SharedKernel\Application\Command;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;
use App\Providers\Modules\SharedKernel\Application\Exception\Refusal;
use App\Providers\Modules\SharedKernel\Domain\DomainException;
use App\Providers\Modules\SharedKernel\Infrastructure\Causation;
use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;
use App\Providers\Modules\SharedKernel\Infrastructure\Http\RefusalStatuses;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Logs every command with its duration and the correlation id of the request, which Laravel adds to each log line.
 *
 * It also gives the command its id and logs the id of the message that caused it: what the command appends points to it.
 */
final class LoggingCommandMiddleware implements CommandMiddleware
{
    public function handle(Command $command, Closure $next): ?CommandResponse
    {
        Correlation::ensure();
        $causedBy = Causation::current();

        return Causation::within(fn (): ?CommandResponse => $this->handleAs(Causation::begin(), $causedBy, $command, $next));
    }

    private function handleAs(string $commandId, ?string $causedBy, Command $command, Closure $next): ?CommandResponse
    {
        $start = hrtime(true);

        try {
            $response = $next($command);
        } catch (DomainException|Refusal $refusal) {
            Log::info('Command refused', $this->context($command, $commandId, $causedBy, $start, ['reason' => $refusal->getMessage(), 'status' => app(RefusalStatuses::class)->of($refusal)]));

            throw $refusal;
        } catch (Throwable $failure) {
            Log::warning('Command failed', $this->context($command, $commandId, $causedBy, $start, ['exception' => $failure::class]));

            throw $failure;
        }

        Log::info('Command handled', $this->context($command, $commandId, $causedBy, $start));

        return $response;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function context(Command $command, string $commandId, ?string $causedBy, int $start, array $extra = []): array
    {
        return [
            'command' => $command::class,
            'command_id' => $commandId,
            'caused_by' => $causedBy,
            'duration_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
            ...$extra,
        ];
    }
}
