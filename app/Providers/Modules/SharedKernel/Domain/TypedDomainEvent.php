<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

/**
 * A fact of the domain, with a shape: a class, value objects, no array. It belongs to its context. What other contexts
 * read is a contract that translates it, or nothing at all when the fact is `#[Internal]`.
 *
 * `DomainEvent` is the envelope the event store keeps; this is the fact it may carry.
 */
interface TypedDomainEvent {}
