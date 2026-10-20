<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware;

use App\Providers\Modules\SharedKernel\Application\Bus\CommandMiddleware;
use App\Providers\Modules\SharedKernel\Application\Bus\Exception\IdempotencyKeyReused;
use App\Providers\Modules\SharedKernel\Application\Command;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;
use App\Providers\Modules\SharedKernel\Application\IdempotentCommand;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Idempotency\ReplayedResponse;
use App\Providers\Modules\SharedKernel\Infrastructure\Tables;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Runs a keyed command once. The first request claims the key, runs the handler and stores its response;
 * a later one with the same payload gets that response back, and with another payload is refused.
 *
 * It must sit inside the transaction: the claim, the handler's writes and the stored response commit together,
 * so a failed handler leaves no key behind. A concurrent request waits for that commit on the claim, then replays it.
 */
final class IdempotencyMiddleware implements CommandMiddleware
{
    public function handle(Command $command, Closure $next): ?CommandResponse
    {
        if (! $command instanceof IdempotentCommand || $command->idempotencyKey() === null) {
            return $next($command);
        }

        return $this->claim($command) ? $this->execute($command, $next) : $this->replay($command);
    }

    private function claim(IdempotentCommand $command): bool
    {
        return DB::table(Tables::IDEMPOTENCY_KEYS)->insertOrIgnore([
            'idempotency_key' => (string) $command->idempotencyKey(),
            'command' => $command::class,
            'fingerprint' => $this->fingerprint($command),
            'created_at' => now(),
        ]) === 1;
    }

    private function execute(IdempotentCommand $command, Closure $next): ?CommandResponse
    {
        $response = $next($command);

        $this->claimed($command)->update(['response' => $response === null ? null : json_encode($response)]);

        return $response;
    }

    private function replay(IdempotentCommand $command): ?CommandResponse
    {
        $stored = $this->claimed($command)->first();

        if ($stored->fingerprint !== $this->fingerprint($command)) {
            throw new IdempotencyKeyReused;
        }

        return $stored->response === null ? null : new ReplayedResponse(json_decode($stored->response, true));
    }

    private function claimed(IdempotentCommand $command): Builder
    {
        return DB::table(Tables::IDEMPOTENCY_KEYS)
            ->where('idempotency_key', (string) $command->idempotencyKey())
            ->where('command', $command::class);
    }

    private function fingerprint(IdempotentCommand $command): string
    {
        return hash('sha256', serialize($command));
    }
}
