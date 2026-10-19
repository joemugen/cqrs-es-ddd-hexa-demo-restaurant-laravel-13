<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Console;

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Projection\ToServeProjector;
use App\Providers\Modules\SharedKernel\Infrastructure\Eventing\EventConsumers;
use Illuminate\Console\Command;

final class RunProjections extends Command
{
    protected $signature = 'projections:run {name=to-serve : The projection to catch up}';

    protected $description = 'Catch a read model up from its checkpoint';

    public function handle(EventConsumers $consumers): int
    {
        if ($this->argument('name') !== 'to-serve') {
            $this->error('Unknown projection ['.$this->argument('name').'].');

            return self::FAILURE;
        }

        $applied = $consumers->make(ToServeProjector::class)->consume();
        $this->info("To-serve projection applied {$applied} ready dish(es).");

        return self::SUCCESS;
    }
}
