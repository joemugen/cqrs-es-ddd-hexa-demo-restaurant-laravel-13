<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Infrastructure\Console;

use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\ContextDiscovery;
use Illuminate\Console\Command;

final class ClearContexts extends Command
{
    protected $signature = 'contexts:clear';

    protected $description = 'Remove the cached context discovery';

    public function handle(ContextDiscovery $discovery): int
    {
        $discovery->clear();

        $this->components->info('Cached contexts removed.');

        return self::SUCCESS;
    }
}
