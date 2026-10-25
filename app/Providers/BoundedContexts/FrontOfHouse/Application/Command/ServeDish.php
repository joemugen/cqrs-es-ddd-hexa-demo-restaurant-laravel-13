<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;
use App\Providers\Modules\SharedKernel\Application\Command;

final readonly class ServeDish implements Command
{
    public function __construct(public DishAtThePassId $dishAtThePassId) {}
}
