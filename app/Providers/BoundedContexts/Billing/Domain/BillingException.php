<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

use App\Providers\BoundedContexts\SharedKernel\Domain\DomainException;

final class BillingException extends DomainException {}
