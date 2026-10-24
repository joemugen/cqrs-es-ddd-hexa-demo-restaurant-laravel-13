<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

/**
 * A value that breaks the rules of its type, caught the moment it is built.
 */
final class InvalidValue extends DomainException implements RuleViolation {}
