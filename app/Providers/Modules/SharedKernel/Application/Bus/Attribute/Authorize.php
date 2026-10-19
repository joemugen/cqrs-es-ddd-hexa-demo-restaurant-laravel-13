<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Bus\Attribute;

use Attribute;

/**
 * Names an ability the caller must hold to send this command or ask this query.
 * The authorization middleware checks it against the Gate before the handler runs.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Authorize
{
    public function __construct(public string $ability) {}
}
