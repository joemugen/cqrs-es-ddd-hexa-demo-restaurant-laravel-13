<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application\Bus\Attribute;

use Attribute;

/**
 * Declares the query a handler answers. The bus checks it against the type of __invoke().
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsQueryHandler
{
    /**
     * @param  class-string  $message
     */
    public function __construct(public string $message) {}
}
