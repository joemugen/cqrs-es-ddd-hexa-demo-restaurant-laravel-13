<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

/**
 * Why a refusal happens: a rule of the business says no. How to say so to the caller is up to the adapter.
 */
interface RuleViolation {}
