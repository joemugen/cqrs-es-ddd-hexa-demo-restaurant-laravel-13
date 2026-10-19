<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware;

use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\Authorize;
use App\Providers\Modules\SharedKernel\Application\Bus\Exception\NotAuthorized;
use Illuminate\Support\Facades\Gate;
use ReflectionClass;

trait ChecksAbilities
{
    /**
     * @throws NotAuthorized
     */
    private function authorize(object $message): void
    {
        foreach ((new ReflectionClass($message))->getAttributes(Authorize::class) as $attribute) {
            if (! Gate::allows($attribute->newInstance()->ability, $message)) {
                throw new NotAuthorized;
            }
        }
    }
}
