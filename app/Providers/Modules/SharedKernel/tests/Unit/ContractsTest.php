<?php

use App\Providers\Modules\SharedKernel\Application\Command;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;
use App\Providers\Modules\SharedKernel\Application\Processor;
use App\Providers\Modules\SharedKernel\Application\Projector;
use App\Providers\Modules\SharedKernel\Application\Query;
use App\Providers\Modules\SharedKernel\Application\QueryHandler;
use App\Providers\Modules\SharedKernel\Application\RebuildableProjector;
use App\Providers\Modules\SharedKernel\Domain\AggregateId;
use App\Providers\Modules\SharedKernel\Domain\AggregateRoot;
use App\Providers\Modules\SharedKernel\Domain\EventSourcedAggregate;
use App\Providers\Modules\SharedKernel\Domain\StringValue;

/**
 * @return list<class-string>
 */
function aggregates(): array
{
    $ns = 'App\\Providers\\BoundedContexts\\';

    return [
        $ns.'Billing\\Domain\\Bill',
        $ns.'FrontOfHouse\\Domain\\DishAtThePass',
        $ns.'FrontOfHouse\\Domain\\MenuItem',
        $ns.'FrontOfHouse\\Domain\\OrderFulfilment',
        $ns.'FrontOfHouse\\Domain\\Table',
        $ns.'FrontOfHouse\\Domain\\TableOrder',
        $ns.'Kitchen\\Domain\\Dish',
        $ns.'Kitchen\\Domain\\KitchenTicket',
    ];
}

function returnTypeOf(string $class, string $method): string
{
    return ltrim((string) (new ReflectionMethod($class, $method))->getReturnType(), '?');
}

describe('application layer', function (): void {
    it('keeps only ports at the root of Application', function (string $class): void {
        expect(interface_exists($class))->toBeTrue("{$class} is not a port");
    })->with(classesIn('*/Application/*.php'));

    describe('commands', function (): void {
        it('declares every command as a Command or a CommandHandler', function (string $class): void {
            $contract = str_ends_with($class, 'Handler') ? CommandHandler::class : Command::class;

            expect(is_subclass_of($class, $contract))->toBeTrue("{$class} does not implement {$contract}");
        })->with(classesIn('*/Application/Command/*.php'));

        it('declares every command response as a CommandResponse', function (string $class): void {
            expect(is_subclass_of($class, CommandResponse::class))->toBeTrue("{$class} is not a CommandResponse");
        })->with(classesIn('*/Application/Command/Response/*.php'));

    });

    describe('queries', function (): void {
        it('declares every query as a Query', function (string $class): void {
            expect(is_subclass_of($class, Query::class))->toBeTrue("{$class} does not implement Query");
        })->with(classesIn('*/Application/Query/*.php'));

        it('declares every query response as JSON serializable', function (string $class): void {
            expect(is_subclass_of($class, JsonSerializable::class))->toBeTrue("{$class} is not JsonSerializable");
        })->with(classesIn('*/Application/Query/Response/*.php'));

        it('answers every query with a QueryHandler', function (string $class): void {
            expect(is_subclass_of($class, QueryHandler::class))->toBeTrue("{$class} does not implement QueryHandler");
        })->with(classesIn('*/Infrastructure/Query/*Handler.php'));

    });
});

describe('domain layer', function (): void {
    describe('aggregates', function (): void {
        it('declares every aggregate as an AggregateRoot', function (string $class): void {
            expect(is_subclass_of($class, AggregateRoot::class))->toBeTrue("{$class} does not implement AggregateRoot");
        })->with(aggregates());

        it('identifies every aggregate by a typed AggregateId', function (string $class): void {
            $type = returnTypeOf($class, 'id');

            expect(is_subclass_of($type, AggregateId::class))->toBeTrue("{$class}::id() returns {$type}");
        })->with(aggregates());

        it('declares the kitchen ticket as an event-sourced aggregate', function (): void {
            expect(is_subclass_of(
                'App\\Providers\\BoundedContexts\\Kitchen\\Domain\\KitchenTicket',
                EventSourcedAggregate::class,
            ))->toBeTrue();
        });
    });

    it('types every identifier and name as a StringValue', function (string $class): void {
        expect(is_subclass_of($class, StringValue::class))->toBeTrue("{$class} is not a StringValue");
    })->with(classesIn('{FrontOfHouse,Kitchen,Billing}/Domain/*{Id,Name,Label,Ref,Key}.php'));
});

describe('infrastructure layer', function (): void {
    describe('controllers', function (): void {
        it('does one thing: __invoke is its only public method', function (string $class): void {
            $methods = array_map(
                fn (ReflectionMethod $method): string => $method->getName(),
                array_filter(
                    (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC),
                    fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class,
                ),
            );

            expect(array_values($methods))->toBe(['__invoke']);
        })->with(classesIn('{FrontOfHouse,Kitchen,Billing}/Infrastructure/Http/*.php'));

        it('is named after its use case and ends with Controller', function (string $class): void {
            expect($class)->toEndWith('Controller');
        })->with(classesIn('{FrontOfHouse,Kitchen,Billing}/Infrastructure/Http/*.php'));
    });

    it('declares every projector as a Projector', function (string $class): void {
        expect(is_subclass_of($class, Projector::class))->toBeTrue("{$class} does not implement Projector");
    })->with(classesIn('*/Infrastructure/Projection/*Projector.php'));

    it('declares every processor as a Processor', function (string $class): void {
        expect(is_subclass_of($class, Processor::class))->toBeTrue("{$class} does not implement Processor");
    })->with(classesIn('*/Infrastructure/Processors/*.php'));

    it('lets the kitchen screen be rebuilt from the first event', function (): void {
        expect(is_subclass_of(
            'App\\Providers\\BoundedContexts\\Kitchen\\Infrastructure\\Projection\\KitchenScreenProjector',
            RebuildableProjector::class,
        ))->toBeTrue();
    });
});
