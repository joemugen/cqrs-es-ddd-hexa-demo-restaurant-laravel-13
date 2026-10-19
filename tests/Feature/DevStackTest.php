<?php

use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

/**
 * @return array<string, mixed>
 */
function composerJson(): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
}

/**
 * @return array<string, mixed>
 */
function composerScripts(): array
{
    return composerJson()['scripts'];
}

/**
 * What `make <target>` would run, without running it.
 */
function plan(string $target): ProcessResult
{
    return Process::path(dirname(__DIR__, 2))
        ->env(['PHP_BINARY' => PHP_BINARY])
        ->run(['make', '-n', $target]);
}

describe('the dev stack', function (): void {
    describe('composer scripts', function (): void {
        it('declares a script per make target', function (string $script, string $target): void {
            expect(composerScripts()[$script] ?? [])->toContain("make {$target}");
        })->with([
            'workers and the API' => ['dev', 'dev'],
            'sync consumption and the queue' => ['dev:sync', 'dev-sync'],
            'only the workers' => ['workers', 'workers'],
        ]);

        it('lifts the composer process timeout, since the stack never ends', function (string $script): void {
            expect(composerScripts()[$script])->toContain('Composer\\Config::disableProcessTimeout');
        })->with(['dev', 'dev:sync', 'workers']);

        it('describes each script', function (string $script): void {
            expect(composerJson()['scripts-descriptions'][$script] ?? null)->toBeString();
        })->with(['dev', 'dev:sync', 'workers']);
    });

    describe('the Makefile', function (): void {
        it('plans the API and one worker per context in worker mode', function (): void {
            $plan = plan('dev');

            expect($plan->successful())->toBeTrue($plan->errorOutput())
                ->and($plan->output())->toContain('EVENT_CONSUMPTION=worker')
                ->toContain('[serve]')
                ->toContain('artisan serve')
                ->toContain('artisan billing:consume')
                ->toContain('artisan front-of-house:consume')
                ->toContain('artisan kitchen:consume')
                ->not->toContain('queue:listen');
        });

        it('plans the API and the queue in sync mode, with no worker', function (): void {
            $plan = plan('dev-sync');

            expect($plan->successful())->toBeTrue($plan->errorOutput())
                ->and($plan->output())->toContain('EVENT_CONSUMPTION=sync')
                ->toContain('artisan serve')
                ->toContain('artisan queue:listen')
                ->not->toContain(':consume');
        });

        it('plans only the workers when asked for them', function (): void {
            $plan = plan('workers');

            expect($plan->successful())->toBeTrue($plan->errorOutput())
                ->and($plan->output())->toContain('artisan kitchen:consume')
                ->not->toContain('artisan serve');
        });

        it('lists its targets by default', function (): void {
            $help = Process::path(dirname(__DIR__, 2))->run(['make']);

            expect($help->successful())->toBeTrue($help->errorOutput())
                ->and($help->output())->toContain('make dev ')
                ->toContain('make dev-sync')
                ->toContain('make workers');
        });

        it('has no rule for an unknown target', function (): void {
            expect(plan('bogus')->exitCode())->toBe(2);
        });
    });
});
