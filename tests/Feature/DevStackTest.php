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

function devStack(string ...$arguments): ProcessResult
{
    return Process::path(dirname(__DIR__, 2))
        ->env(['PHP_BINARY' => PHP_BINARY])
        ->run(['bash', 'scripts/dev.sh', ...$arguments]);
}

describe('the dev stack', function (): void {
    describe('composer scripts', function (): void {
        it('declares a script per mode', function (string $script, string $mode): void {
            expect(composerScripts()[$script] ?? [])->toContain("bash scripts/dev.sh {$mode}");
        })->with([
            'workers and the API' => ['dev', 'worker'],
            'sync consumption and the queue' => ['dev:sync', 'sync'],
            'only the workers' => ['workers', 'workers'],
        ]);

        it('lifts the composer process timeout, since the stack never ends', function (string $script): void {
            expect(composerScripts()[$script])->toContain('Composer\\Config::disableProcessTimeout');
        })->with(['dev', 'dev:sync', 'workers']);

        it('describes each script', function (string $script): void {
            expect(composerJson()['scripts-descriptions'][$script] ?? null)->toBeString();
        })->with(['dev', 'dev:sync', 'workers']);
    });

    describe('scripts/dev.sh', function (): void {
        it('plans the API and one worker per context in worker mode', function (): void {
            $plan = devStack('worker', '--dry-run');

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
            $plan = devStack('sync', '--dry-run');

            expect($plan->successful())->toBeTrue($plan->errorOutput())
                ->and($plan->output())->toContain('EVENT_CONSUMPTION=sync')
                ->toContain('artisan serve')
                ->toContain('artisan queue:listen')
                ->not->toContain(':consume');
        });

        it('plans only the workers when asked for them', function (): void {
            $plan = devStack('workers', '--dry-run');

            expect($plan->successful())->toBeTrue($plan->errorOutput())
                ->and($plan->output())->toContain('artisan kitchen:consume')
                ->not->toContain('artisan serve');
        });

        it('refuses an unknown mode before touching anything', function (): void {
            $plan = devStack('bogus');

            expect($plan->exitCode())->toBe(2)
                ->and($plan->errorOutput())->toContain('Usage');
        });
    });
});
