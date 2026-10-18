<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application;

/**
 * Folds the event log into a read model from its checkpoint. It decides nothing.
 * consume() returns how many events changed the read model.
 */
interface Projector extends EventConsumer {}
