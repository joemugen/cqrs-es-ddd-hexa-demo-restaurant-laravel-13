<?php

use App\Providers\BoundedContexts\Billing\Application\Command\PayBillHandler;
use App\Providers\BoundedContexts\Billing\Infrastructure\Bus\BillingCommandBus;
use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\HandlerDiscovery;
use Illuminate\Support\ServiceProvider;

function contextsRoot(): string
{
    return app_path('Providers/BoundedContexts');
}

function discoveryWithCacheAt(string $path): HandlerDiscovery
{
    app()->instance(HandlerDiscovery::class, $discovery = new HandlerDiscovery($path));

    return $discovery;
}

beforeEach(function (): void {
    $this->cachePath = sys_get_temp_dir().'/bus-handlers-'.bin2hex(random_bytes(4)).'.php';
});

afterEach(function (): void {
    @unlink($this->cachePath);
});

describe('handler discovery', function (): void {
    describe('without a cache', function (): void {
        it('finds the command handlers of a context', function (string $context): void {
            $found = discoveryWithCacheAt($this->cachePath)->commandHandlers(contextsRoot().'/'.$context);

            expect($found)->toEqualCanonicalizing(classesIn($context.'/Application/Command/*Handler.php'));
        })->with(['FrontOfHouse', 'Kitchen', 'Billing']);

        it('finds the query handlers of a context', function (string $context): void {
            $found = discoveryWithCacheAt($this->cachePath)->queryHandlers(contextsRoot().'/'.$context);

            expect($found)->toEqualCanonicalizing(classesIn($context.'/Infrastructure/Query/*Handler.php'));
        })->with(['FrontOfHouse', 'Kitchen', 'Billing']);

        it('finds nothing in a context without handlers', function (): void {
            $discovery = discoveryWithCacheAt($this->cachePath);

            expect($discovery->commandHandlers(contextsRoot().'/EventStore'))->toBe([])
                ->and($discovery->queryHandlers(contextsRoot().'/EventStore'))->toBe([]);
        });
    });

    describe('with a cache', function (): void {
        it('writes every context to a file and reads it back', function (): void {
            $discovery = discoveryWithCacheAt($this->cachePath);

            $path = $discovery->cache(contextsRoot());

            $cached = new HandlerDiscovery($this->cachePath);

            expect($path)->toBe($this->cachePath)
                ->and(file_exists($this->cachePath))->toBeTrue()
                ->and($cached->commandHandlers(contextsRoot().'/Billing'))
                ->toEqualCanonicalizing($discovery->commandHandlers(contextsRoot().'/Billing'));
        });

        it('serves what the file says instead of scanning', function (): void {
            file_put_contents($this->cachePath, '<?php return '.var_export([
                'app/Providers/BoundedContexts/Billing' => ['commands' => [PayBillHandler::class], 'queries' => []],
            ], true).';');

            expect((new HandlerDiscovery($this->cachePath))->commandHandlers(contextsRoot().'/Billing'))
                ->toBe([PayBillHandler::class]);
        });

        it('feeds the bus of the context', function (): void {
            file_put_contents($this->cachePath, '<?php return '.var_export([
                'app/Providers/BoundedContexts/Billing' => ['commands' => [PayBillHandler::class], 'queries' => []],
            ], true).';');
            discoveryWithCacheAt($this->cachePath);

            expect(array_values((new BillingCommandBus(app()))->handlerMap()->handlers()))->toBe([PayBillHandler::class]);
        });

        it('is created by bus:cache and removed by bus:clear', function (): void {
            discoveryWithCacheAt($this->cachePath);

            $this->artisan('bus:cache')->assertSuccessful();
            expect(file_exists($this->cachePath))->toBeTrue();

            $this->artisan('bus:clear')->assertSuccessful();
            expect(file_exists($this->cachePath))->toBeFalse();
        });

        it('is registered with php artisan optimize and optimize:clear', function (): void {
            expect(ServiceProvider::$optimizeCommands)->toContain('bus:cache')
                ->and(ServiceProvider::$optimizeClearCommands)->toContain('bus:clear');
        });
    });
});
