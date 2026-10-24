<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\ObsoleteRevision;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitIsClosed;
use App\Providers\Modules\SharedKernel\Application\Bus\Exception\NotAuthorized;
use App\Providers\Modules\SharedKernel\Application\Concurrency\ConcurrencyConflict;
use App\Providers\Modules\SharedKernel\Application\Concurrency\PreconditionFailed;
use App\Providers\Modules\SharedKernel\Application\Exception\Refusal;
use App\Providers\Modules\SharedKernel\Domain\DomainException;
use App\Providers\Modules\SharedKernel\Domain\InvalidValue;
use App\Providers\Modules\SharedKernel\Domain\NotFound;
use App\Providers\Modules\SharedKernel\Domain\RuleViolation;
use App\Providers\Modules\SharedKernel\Infrastructure\Http\RefusalStatuses;

/**
 * @return list<class-string<DomainException>>
 */
function everyRefusalOfTheApplication(): array
{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Providers'), FilesystemIterator::SKIP_DOTS));
    $refusals = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/tests/')) {
            continue;
        }

        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen(app_path()) + 1));

        if (class_exists($class) && (is_subclass_of($class, DomainException::class) || is_subclass_of($class, Refusal::class)) && ! (new ReflectionClass($class))->isAbstract()) {
            $refusals[] = $class;
        }
    }

    return $refusals;
}

describe('the status of a refusal is the business of the HTTP adapter, never of the domain', function (): void {
    it('says why each exception of a domain refuses, with a category the domain owns', function (): void {
        $uncategorized = array_filter(
            everyRefusalOfTheApplication(),
            fn (string $class): bool => str_contains($class, '\\Domain\\')
                && ! is_subclass_of($class, NotFound::class)
                && ! is_subclass_of($class, RuleViolation::class),
        );

        expect($uncategorized)->toBe([]);
    });

    it('has a status for every refusal, so none ends as a silent 500', function (): void {
        $statuses = app(RefusalStatuses::class);

        $unmapped = array_filter(everyRefusalOfTheApplication(), fn (string $class): bool => $statuses->ofClass($class) === 500);

        expect(array_values($unmapped))->toBe([]);
    });

    it('answers by category unless a class asks for better', function (): void {
        $statuses = app(RefusalStatuses::class);

        expect($statuses->ofClass(InvalidValue::class))->toBe(422)
            ->and($statuses->ofClass(VisitIsClosed::class))->toBe(409)
            ->and($statuses->ofClass(ObsoleteRevision::class))->toBe(412)
            ->and($statuses->ofClass(PreconditionFailed::class))->toBe(412)
            ->and($statuses->ofClass(ConcurrencyConflict::class))->toBe(409)
            ->and($statuses->ofClass(NotAuthorized::class))->toBe(403);
    });

    it('gives a 500 to what it does not know', function (): void {
        expect(app(RefusalStatuses::class)->ofClass(RuntimeException::class))->toBe(500);
    });
});
