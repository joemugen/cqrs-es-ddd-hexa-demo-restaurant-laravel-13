<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Infrastructure\Console;

use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\ContextDiscovery;
use Illuminate\Console\Command;

final class CacheContexts extends Command
{
    protected $signature = 'contexts:cache';

    protected $description = 'Discover the handlers and event consumers of every bounded context and cache them';

    public function handle(ContextDiscovery $discovery): int
    {
        $path = $discovery->cache(app_path('Providers/BoundedContexts'));

        $this->components->info('Contexts cached: '.$path);

        return self::SUCCESS;
    }
}
