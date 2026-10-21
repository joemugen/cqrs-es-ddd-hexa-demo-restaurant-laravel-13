<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

use Attribute;

/**
 * This fact of the domain stays inside its context, on purpose. The alternative is a contract that translates it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Internal {}
