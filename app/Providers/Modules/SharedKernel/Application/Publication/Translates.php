<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Publication;

use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;
use Attribute;

/**
 * The fact of the domain a contract is built from. The contract has a static `from()` that takes this fact.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Translates
{
    /**
     * @param  class-string<TypedDomainEvent>  $event
     */
    public function __construct(public string $event) {}
}
