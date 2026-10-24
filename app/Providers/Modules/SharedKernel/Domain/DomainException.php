<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

use RuntimeException;

/**
 * A business refusal: a message for the caller. The domain says why it refuses (a category such as NotFound or
 * RuleViolation), never how a transport says it.
 */
abstract class DomainException extends RuntimeException {}
