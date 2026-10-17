<?php

use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;
use App\Providers\BoundedContexts\SharedKernel\Application\Processor;
use App\Providers\BoundedContexts\SharedKernel\Application\Projector;
use App\Providers\BoundedContexts\SharedKernel\Application\Query;
use App\Providers\BoundedContexts\SharedKernel\Application\QueryHandler;
use App\Providers\BoundedContexts\SharedKernel\Application\RebuildableProjector;
use App\Providers\BoundedContexts\SharedKernel\Domain\AggregateRoot;
use App\Providers\BoundedContexts\SharedKernel\Domain\EventSourcedAggregate;

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

it('declares every query as a Query', function (string $class): void {
    expect(is_subclass_of($class, Query::class))->toBeTrue("{$class} does not implement Query");
})->with(classesIn('*/Application/Query/*.php'));

it('answers every query with a QueryHandler', function (string $class): void {
    expect(is_subclass_of($class, QueryHandler::class))->toBeTrue("{$class} does not implement QueryHandler");
})->with(classesIn('*/Infrastructure/Query/*Handler.php'));

it('declares every aggregate as an AggregateRoot', function (string $class): void {
    expect(is_subclass_of($class, AggregateRoot::class))->toBeTrue("{$class} does not implement AggregateRoot");
})->with([
    'App\\Providers\\BoundedContexts\\Billing\\Domain\\Bill',
    'App\\Providers\\BoundedContexts\\FrontOfHouse\\Domain\\DishAtThePass',
    'App\\Providers\\BoundedContexts\\FrontOfHouse\\Domain\\MenuItem',
    'App\\Providers\\BoundedContexts\\FrontOfHouse\\Domain\\Table',
    'App\\Providers\\BoundedContexts\\FrontOfHouse\\Domain\\TableOrder',
    'App\\Providers\\BoundedContexts\\Kitchen\\Domain\\Dish',
    'App\\Providers\\BoundedContexts\\Kitchen\\Domain\\KitchenTicket',
]);

it('declares the kitchen ticket as an event-sourced aggregate', function (): void {
    expect(is_subclass_of('App\\Providers\\BoundedContexts\\Kitchen\\Domain\\KitchenTicket', EventSourcedAggregate::class))->toBeTrue();
});

it('declares every projector as a Projector', function (string $class): void {
    expect(is_subclass_of($class, Projector::class))->toBeTrue("{$class} does not implement Projector");
})->with(classesIn('*/Infrastructure/Projection/*Projector.php'));

it('declares every processor as a Processor', function (string $class): void {
    expect(is_subclass_of($class, Processor::class))->toBeTrue("{$class} does not implement Processor");
})->with(classesIn('*/Infrastructure/Processors/*.php'));

it('lets the kitchen screen be rebuilt from the first event', function (): void {
    expect(is_subclass_of('App\\Providers\\BoundedContexts\\Kitchen\\Infrastructure\\Projection\\KitchenScreenProjector', RebuildableProjector::class))->toBeTrue();
});
