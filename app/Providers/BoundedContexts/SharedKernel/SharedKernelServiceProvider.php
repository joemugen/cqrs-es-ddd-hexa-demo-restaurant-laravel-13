<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel;

use App\Providers\BoundedContexts\SharedKernel\Domain\DomainException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\ServiceProvider;

final class SharedKernelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        if ($handler instanceof Handler) {
            $handler->dontReport(DomainException::class);
            $handler->renderable(function (DomainException $exception) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], $exception->status());
            });
        }
    }
}
