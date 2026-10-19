<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Bus\Exception;

use App\Providers\Modules\SharedKernel\Domain\DomainException;

final class NotAuthorized extends DomainException
{
    public function __construct()
    {
        parent::__construct('You are not allowed to do that.', 403);
    }
}
