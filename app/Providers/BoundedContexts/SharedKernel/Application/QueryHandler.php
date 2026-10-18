<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application;

/**
 * Answers one query. It declares which with #[AsQueryHandler] and exposes a typed __invoke(), like a CommandHandler.
 *
 * @template TQuery of Query
 * @template TResponse of QueryResponse
 */
interface QueryHandler {}
