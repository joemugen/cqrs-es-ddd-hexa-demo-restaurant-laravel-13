<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Concurrency;

/**
 * Why a refusal happens: somebody decided on a state of the resource that is no longer the current one.
 */
interface StaleVersion {}
