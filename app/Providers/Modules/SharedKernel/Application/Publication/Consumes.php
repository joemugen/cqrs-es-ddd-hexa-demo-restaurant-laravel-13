<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Publication;

use Attribute;

/**
 * The contract, by its name and version, that a message of a context reads. The name is copied from the contract of the
 * context that publishes it: no context imports the class of another.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Consumes
{
    public function __construct(
        public string $type,
        public int $version,
    ) {}
}
