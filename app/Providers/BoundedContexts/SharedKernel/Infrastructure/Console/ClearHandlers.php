<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Infrastructure\Console;

use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\HandlerDiscovery;
use Illuminate\Console\Command;

final class ClearHandlers extends Command
{
    protected $signature = 'bus:clear';

    protected $description = 'Remove the cached bus handlers';

    public function handle(HandlerDiscovery $discovery): int
    {
        $discovery->clear();

        $this->components->info('Cached bus handlers removed.');

        return self::SUCCESS;
    }
}
