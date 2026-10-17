<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application;

use JsonSerializable;

/**
 * What a command handler tells its caller: the command was accepted, and for which aggregate.
 */
interface CommandResponse extends JsonSerializable
{
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array;
}
