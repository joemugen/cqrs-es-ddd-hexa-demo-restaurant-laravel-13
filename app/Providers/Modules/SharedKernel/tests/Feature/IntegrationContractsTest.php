<?php

use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;
use App\Providers\Modules\SharedKernel\Domain\Internal;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\ContextDiscovery;

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

    it('is told through the ContractPublisher: no context builds an integration event or holds the bus itself', function (): void {
        $files = glob(app_path('Providers/BoundedContexts/*/{Application,Domain,Infrastructure}/{,*/,*/*/}*.php'), GLOB_BRACE);
        $offenders = array_values(array_filter(
            $files,
            fn (string $file): bool => preg_match('/\\b(IntegrationEvent|IntegrationBus)\\b/', (string) file_get_contents($file)) === 1,
        ));

        expect(array_map('basename', $offenders))->toBe([]);
    });

    describe('each message a context reads', function (): void {
        it('is a ConsumedMessage that says which contract and version it reads', function (string $class): void {
            expect(is_subclass_of($class, ConsumedMessage::class))->toBeTrue("{$class} is not a ConsumedMessage")
                ->and((new ReflectionClass($class))->getAttributes(Consumes::class))->toHaveCount(1, "{$class} needs #[Consumes]");
        })->with(array_filter(classesIn('*/Infrastructure/Consumed/*.php'), fn (string $class): bool => ! (new ReflectionClass($class))->isAbstract()));

        it('reads a contract that a context publishes, under that name and that version', function (string $class): void {
            $consumes = (new ReflectionClass($class))->getAttributes(Consumes::class)[0]->newInstance();
            $published = array_map(function (string $contract): string {
                $declared = (new ReflectionClass($contract))->getAttributes(PublishedAs::class)[0]->newInstance();

                return "{$declared->type} v{$declared->version}";
            }, classesIn('*/Application/Integration/*.php'));

            expect($published)->toContain("{$consumes->type} v{$consumes->version}");
        })->with(array_filter(classesIn('*/Infrastructure/Consumed/*.php'), fn (string $class): bool => ! (new ReflectionClass($class))->isAbstract()));
    });

    describe('discovery', function (): void {
        it('finds the contracts of a context, with or without the cache', function (): void {
            $path = sys_get_temp_dir().'/contracts-'.bin2hex(random_bytes(4)).'.php';
            $discovery = new ContextDiscovery($path, config('contexts'));
            $withoutCache = $discovery->contracts(config('contexts.namespace').'\\Kitchen');

            $discovery->cache();
            $withCache = (new ContextDiscovery($path, config('contexts')))->contracts(config('contexts.namespace').'\\Kitchen');
            @unlink($path);

            expect($withoutCache)->toEqualCanonicalizing(classesIn('Kitchen/Application/Integration/*.php'))
                ->and($withCache)->toBe($withoutCache);
        });

        it('finds the messages a context reads, with or without the cache', function (): void {
            $path = sys_get_temp_dir().'/consumed-'.bin2hex(random_bytes(4)).'.php';
            $discovery = new ContextDiscovery($path, config('contexts'));
            $context = config('contexts.namespace').'\\FrontOfHouse';
            $withoutCache = $discovery->consumedMessages($context);

            $discovery->cache();
            $withCache = (new ContextDiscovery($path, config('contexts')))->consumedMessages($context);
            @unlink($path);

            expect($withoutCache)->toEqualCanonicalizing(array_values(array_filter(
                classesIn('FrontOfHouse/Infrastructure/Consumed/*.php'),
                fn (string $class): bool => ! (new ReflectionClass($class))->isAbstract(),
            )))->and($withCache)->toBe($withoutCache);
        });
    });
});
