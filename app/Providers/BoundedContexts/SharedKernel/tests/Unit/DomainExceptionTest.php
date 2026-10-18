<?php

use App\Providers\BoundedContexts\SharedKernel\Domain\DomainException;
use App\Providers\BoundedContexts\SharedKernel\Domain\StreamId;

final class TestingDomainException extends DomainException {}

describe('a domain exception', function (): void {
    it('keeps its message as the translation key when nothing is replaced', function (): void {
        $exception = new TestingDomainException('That dish has already left the pass.');

        expect($exception->getMessage())->toBe('That dish has already left the pass.')
            ->and($exception->translationKey())->toBe('That dish has already left the pass.')
            ->and($exception->replacements())->toBe([])
            ->and($exception->status())->toBe(422);
    });

    it('fills the placeholders of its message and keeps them for the translator', function (): void {
        $exception = new TestingDomainException('Unknown table :id.', 404, ['id' => 'table-9']);

        expect($exception->getMessage())->toBe('Unknown table table-9.')
            ->and($exception->translationKey())->toBe('Unknown table :id.')
            ->and($exception->replacements())->toBe(['id' => 'table-9'])
            ->and($exception->status())->toBe(404);
    });

    it('accepts stringable replacements', function (): void {
        $exception = new TestingDomainException('Unknown stream :id.', replace: ['id' => new StreamId('kitchen-ticket-1')]);

        expect($exception->getMessage())->toBe('Unknown stream kitchen-ticket-1.')
            ->and($exception->replacements())->toBe(['id' => 'kitchen-ticket-1']);
    });
});
