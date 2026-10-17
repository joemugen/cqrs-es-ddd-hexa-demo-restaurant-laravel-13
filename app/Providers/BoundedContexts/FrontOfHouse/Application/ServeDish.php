<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

final readonly class ServeDish
{
    public function __construct(public string $toServeId) {}
}
