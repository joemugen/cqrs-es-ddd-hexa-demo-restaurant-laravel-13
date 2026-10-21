<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Publication;

use Attribute;

/**
 * The name and the version under which a contract is told to the other contexts.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class PublishedAs
{
    public function __construct(
        public string $type,
        public int $version,
    ) {}
}
