<?php

use App\Providers\BoundedContexts\SharedKernel\Domain\DomainException;

final class TestingDomainException extends DomainException {}

describe('a domain exception', function (): void {
    it('is a 422 unless it says otherwise', function (): void {
        $exception = new TestingDomainException('That dish has already left the pass.');

        expect($exception->getMessage())->toBe('That dish has already left the pass.')
            ->and($exception->status())->toBe(422);
    });

    it('carries the status it was given', function (): void {
        expect((new TestingDomainException('Unknown table table-9.', 404))->status())->toBe(404);
    });
});
