<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Concurrency;

use App\Providers\Modules\SharedKernel\Application\Exception\Refusal;

/**
 * The command is only valid for a state of the resource that the client must name, and it named none.
 */
final class PreconditionRequired extends Refusal {}
