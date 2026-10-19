<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(
        'Feature',
        '../app/Providers/BoundedContexts/*/tests/Feature',
        '../app/Providers/Modules/*/tests/Feature',
    );

/**
 * Where the code lives: bounded contexts, and the modules (shared kernel, event store) they build on.
 *
 * @return list<string>
 */
function sourceRoots(): array
{
    return [
        dirname(__DIR__).'/app/Providers/BoundedContexts',
        dirname(__DIR__).'/app/Providers/Modules',
    ];
}

/**
 * Fully qualified names of the classes, in contexts and modules, whose file matches a glob such as
 * "*\/Application/Command/*Handler.php".
 *
 * @return list<class-string>
 */
function classesIn(string $pattern): array
{
    $app = dirname(__DIR__).'/app/';
    $classes = [];

    foreach (sourceRoots() as $root) {
        foreach (glob($root.'/'.$pattern, GLOB_BRACE) ?: [] as $file) {
            $classes[] = 'App\\'.str_replace(['/', '.php'], ['\\', ''], substr($file, strlen($app)));
        }
    }

    return $classes;
}
