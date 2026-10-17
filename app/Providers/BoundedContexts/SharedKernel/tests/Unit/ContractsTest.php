<?php

use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;

/**
 * @return list<class-string>
 */
function classesIn(string $pattern): array
{
    $root = dirname(__DIR__, 3);

    return array_map(
        fn (string $file): string => 'App\\Providers\\BoundedContexts\\'
            .str_replace(['/', '.php'], ['\\', ''], substr($file, strlen($root) + 1)),
        glob($root.'/'.$pattern),
    );
}

it('keeps only ports at the root of Application', function (string $class): void {
    expect(interface_exists($class))->toBeTrue("{$class} is not a port");
})->with(classesIn('*/Application/*.php'));

it('declares every command as a Command or a CommandHandler', function (string $class): void {
    $contract = str_ends_with($class, 'Handler') ? CommandHandler::class : Command::class;

    expect(is_subclass_of($class, $contract))->toBeTrue("{$class} does not implement {$contract}");
})->with(classesIn('*/Application/Command/*.php'));
