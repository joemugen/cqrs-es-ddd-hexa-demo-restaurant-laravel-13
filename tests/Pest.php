<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(
        'Feature',
        '../app/Providers/BoundedContexts/*/tests/Feature',
    );

/**
 * Fully qualified names of the context classes whose file matches a glob such as "*\/Application/Command/*Handler.php".
 *
 * @return list<class-string>
 */
function classesIn(string $pattern): array
{
    $root = dirname(__DIR__).'/app/Providers/BoundedContexts';

    return array_map(
        fn (string $file): string => 'App\\Providers\\BoundedContexts\\'
            .str_replace(['/', '.php'], ['\\', ''], substr($file, strlen($root) + 1)),
        glob($root.'/'.$pattern, GLOB_BRACE),
    );
}
