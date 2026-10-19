<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware;

use App\Providers\Modules\SharedKernel\Application\Bus\CommandMiddleware;
use App\Providers\Modules\SharedKernel\Application\Command;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;
use App\Providers\Modules\SharedKernel\Domain\DomainException;
use Closure;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Logs every command with its duration and the correlation id of the request, which Laravel adds to each log line.
 */
final class LoggingCommandMiddleware implements CommandMiddleware
{
    public function handle(Command $command, Closure $next): ?CommandResponse
    {
        Context::addIf('correlation_id', (string) Str::uuid());
        $start = hrtime(true);

        try {
            $response = $next($command);
        } catch (DomainException $refusal) {
            Log::info('Command refused', $this->context($command, $start, ['reason' => $refusal->getMessage(), 'status' => $refusal->status()]));

            throw $refusal;
        } catch (Throwable $failure) {
            Log::warning('Command failed', $this->context($command, $start, ['exception' => $failure::class]));

            throw $failure;
        }

        Log::info('Command handled', $this->context($command, $start));

        return $response;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function context(Command $command, int $start, array $extra = []): array
    {
        return [
            'command' => $command::class,
            'duration_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
            ...$extra,
        ];
    }
}
