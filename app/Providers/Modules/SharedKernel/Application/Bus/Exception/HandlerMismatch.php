<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Bus\Exception;

use LogicException;

/**
 * A handler does not honour its contract. It is a wiring bug, found when the bus is built, never a user error.
 */
final class HandlerMismatch extends LogicException {}
