<?php

use App\Providers\BoundedContexts\SharedKernel\Application\Bus\CommandBus;
use App\Providers\BoundedContexts\SharedKernel\Application\Bus\QueryBus;

function contextOf(string $class): string
{
    return explode('\\', $class)[3];
}

describe('bus wiring', function (): void {
    describe('command buses', function (): void {
        it('is a CommandBus', function (string $bus): void {
            expect(is_subclass_of($bus, CommandBus::class))->toBeTrue("{$bus} is not a CommandBus");
        })->with(classesIn('{FrontOfHouse,Kitchen,Billing}/Infrastructure/Bus/*CommandBus.php'));

        it('handles every command handler of its context and no other', function (string $bus): void {
            expect(array_values(app($bus)->handlerMap()->handlers()))
                ->toEqualCanonicalizing(classesIn(contextOf($bus).'/Application/Command/*Handler.php'));
        })->with(classesIn('{FrontOfHouse,Kitchen,Billing}/Infrastructure/Bus/*CommandBus.php'));

        it('exists for every context that has commands', function (string $context): void {
            expect(classesIn($context.'/Infrastructure/Bus/*CommandBus.php'))->toHaveCount(1);
        })->with(['FrontOfHouse', 'Kitchen', 'Billing']);
    });

    describe('query buses', function (): void {
        it('is a QueryBus', function (string $bus): void {
            expect(is_subclass_of($bus, QueryBus::class))->toBeTrue("{$bus} is not a QueryBus");
        })->with(classesIn('{FrontOfHouse,Kitchen,Billing}/Infrastructure/Bus/*QueryBus.php'));

        it('answers every query handler of its context and no other', function (string $bus): void {
            expect(array_values(app($bus)->handlerMap()->handlers()))
                ->toEqualCanonicalizing(classesIn(contextOf($bus).'/Infrastructure/Query/*Handler.php'));
        })->with(classesIn('{FrontOfHouse,Kitchen,Billing}/Infrastructure/Bus/*QueryBus.php'));

        it('exists for every context that has queries', function (string $context): void {
            expect(classesIn($context.'/Infrastructure/Bus/*QueryBus.php'))->toHaveCount(1);
        })->with(['FrontOfHouse', 'Kitchen', 'Billing']);
    });
});
