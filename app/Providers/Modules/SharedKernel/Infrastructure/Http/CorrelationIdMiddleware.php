<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Http;

use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The first and last stop of a request: it reads the correlation id the caller sent, or makes one, and returns it
 * in the response, a refusal included, so the caller can quote it when something goes wrong.
 */
final class CorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = Correlation::begin($request->header(Correlation::HEADER) ?? $request->header('X-Request-Id'));

        $response = $next($request);
        $response->headers->set(Correlation::HEADER, $id);

        return $response;
    }
}
