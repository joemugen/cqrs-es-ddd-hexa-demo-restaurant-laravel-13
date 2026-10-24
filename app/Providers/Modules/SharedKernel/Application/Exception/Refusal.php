<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Exception;

use RuntimeException;

/**
 * An application refusal: the bus or a repository says no, and no rule of the business is involved (not allowed, a key
 * reused, a version that moved). A rule of the business refuses with a DomainException.
 */
abstract class Refusal extends RuntimeException {}
