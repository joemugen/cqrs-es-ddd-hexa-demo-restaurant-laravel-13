<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Bus\Exception;

use LogicException;

final class NoHandlerRegistered extends LogicException
{
    public static function for(object $message): self
    {
        return new self('No handler is registered for '.$message::class.'.');
    }
}
