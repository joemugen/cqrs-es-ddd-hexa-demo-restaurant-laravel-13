<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application;

use App\Providers\Modules\SharedKernel\Domain\IdempotencyKey;

/**
 * A command that can be sent twice and happen once. It carries the key the caller chose,
 * or none when the caller did not ask for the guarantee.
 */
interface IdempotentCommand extends Command
{
    public function idempotencyKey(): ?IdempotencyKey;
}
