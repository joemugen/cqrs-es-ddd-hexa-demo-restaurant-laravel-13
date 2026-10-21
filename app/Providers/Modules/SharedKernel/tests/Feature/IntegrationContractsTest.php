<?php

use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;
use App\Providers\Modules\SharedKernel\Domain\Internal;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\ContextDiscovery;

function contextsRootOfContracts(): string
{
    return app_path('Providers/BoundedContexts');
}

/**
 * @return list<class-string>
 */
function translatedEvents(): array
{
    return array_values(array_unique(array_map(
        fn (string $contract): string => (new ReflectionClass($contract))->getAttributes(Translates::class)[0]->newInstance()->event,
        classesIn('*/Application/Integration/*.php'),
    )));
}

describe('integration contracts', function (): void {
    describe('each contract', function (): void {
        it('is an IntegrationContract', function (string $class): void {
            expect(is_subclass_of($class, IntegrationContract::class))->toBeTrue("{$class} does not implement IntegrationContract");
        })->with(classesIn('*/Application/Integration/*.php'));

        it('says what it is published as, and which fact of the domain it translates', function (string $class): void {
            $reflection = new ReflectionClass($class);

            expect($reflection->getAttributes(PublishedAs::class))->toHaveCount(1, "{$class} needs #[PublishedAs]")
                ->and($reflection->getAttributes(Translates::class))->toHaveCount(1, "{$class} needs #[Translates]");
        })->with(classesIn('*/Application/Integration/*.php'));

        it('is built from the fact it translates, by a static from()', function (string $class): void {
            $event = (new ReflectionClass($class))->getAttributes(Translates::class)[0]->newInstance()->event;
            $parameter = (new ReflectionMethod($class, 'from'))->getParameters()[0];

            expect((string) $parameter->getType())->toBe($event)
                ->and(is_subclass_of($event, TypedDomainEvent::class))->toBeTrue("{$event} is not a TypedDomainEvent");
        })->with(classesIn('*/Application/Integration/*.php'));
    });

    it('never publishes two contracts under the same name and version', function (): void {
        $declared = array_map(function (string $class): string {
            $published = (new ReflectionClass($class))->getAttributes(PublishedAs::class)[0]->newInstance();

            return "{$published->type} v{$published->version}";
        }, classesIn('*/Application/Integration/*.php'));

        expect($declared)->toBe(array_values(array_unique($declared)));
    });

    describe('each fact of the domain that is typed', function (): void {
        it('is either internal on purpose or translated into a contract', function (string $class): void {
            $internal = (new ReflectionClass($class))->getAttributes(Internal::class) !== [];

            expect($internal || in_array($class, translatedEvents(), true))
                ->toBeTrue("{$class} is neither #[Internal] nor translated: decide now whether others will read it");
        })->with(classesIn('*/Domain/Event/*.php'));
    });

    describe('discovery', function (): void {
        it('finds the contracts of a context, with or without the cache', function (): void {
            $path = sys_get_temp_dir().'/contracts-'.bin2hex(random_bytes(4)).'.php';
            $discovery = new ContextDiscovery($path);
            $withoutCache = $discovery->contracts(contextsRootOfContracts().'/Kitchen');

            $discovery->cache(contextsRootOfContracts());
            $withCache = (new ContextDiscovery($path))->contracts(contextsRootOfContracts().'/Kitchen');
            @unlink($path);

            expect($withoutCache)->toEqualCanonicalizing(classesIn('Kitchen/Application/Integration/*.php'))
                ->and($withCache)->toBe($withoutCache);
        });
    });
});
