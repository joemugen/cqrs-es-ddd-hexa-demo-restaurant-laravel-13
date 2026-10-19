<?php

use App\Providers\Modules\SharedKernel\Domain\NamespacedUuid;

const DNS_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

describe('a namespaced UUID', function (): void {
    it('follows the RFC 4122 name-based algorithm', function (): void {
        expect(NamespacedUuid::v5(DNS_NAMESPACE, 'python.org'))->toBe('886313e1-3b8a-5372-9b90-0c9aee199e5d');
    });

    it('is a version 5 UUID with the RFC variant', function (): void {
        expect(NamespacedUuid::generate('table-order', 'table-4:key-1'))
            ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
    });

    it('is the same for the same aggregate and the same name', function (): void {
        expect(NamespacedUuid::generate('bill', 'table-4:12'))->toBe(NamespacedUuid::generate('bill', 'table-4:12'));
    });

    it('differs when the name differs', function (): void {
        expect(NamespacedUuid::generate('bill', 'table-4:12'))->not->toBe(NamespacedUuid::generate('bill', 'table-4:13'));
    });

    it('differs between aggregates for the same name, so a bill id is never a table order id', function (): void {
        expect(NamespacedUuid::generate('bill', 'table-4:12'))->not->toBe(NamespacedUuid::generate('table-order', 'table-4:12'));
    });

    it('derives the namespace of an aggregate from the application namespace', function (): void {
        expect(NamespacedUuid::APPLICATION)
            ->toBe(NamespacedUuid::v5(DNS_NAMESPACE, 'cqrs-restaurant-post-demo-laravel.joemugen'));
    });

    it('is accepted as an identifier', function (): void {
        expect(strlen(NamespacedUuid::generate('bill', 'x')))->toBeLessThanOrEqual(64);
    });
});
