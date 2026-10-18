<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Infrastructure\Console;

use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\HandlerDiscovery;
use Illuminate\Console\Command;

final class CacheHandlers extends Command
{
    protected $signature = 'bus:cache';

    protected $description = 'Discover the command and query handlers of every bounded context and cache them';

    public function handle(HandlerDiscovery $discovery): int
    {
        $path = $discovery->cache(app_path('Providers/BoundedContexts'));

        $this->components->info('Bus handlers cached: '.$path);

        return self::SUCCESS;
    }
}
