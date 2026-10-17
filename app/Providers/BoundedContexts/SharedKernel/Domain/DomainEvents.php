<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Domain;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * An ordered, immutable run of facts: the history of an aggregate or what it has yet to commit.
 *
 * @implements IteratorAggregate<int, DomainEvent>
 */
final readonly class DomainEvents implements Countable, IteratorAggregate
{
    /**
     * @param  list<DomainEvent>  $events
     */
    public function __construct(private array $events = []) {}

    public static function of(DomainEvent ...$events): self
    {
        return new self(array_values($events));
    }

    public function with(DomainEvent $event): self
    {
        return new self([...$this->events, $event]);
    }

    public function isEmpty(): bool
    {
        return $this->events === [];
    }

    public function count(): int
    {
        return count($this->events);
    }

    /**
     * @return Traversable<int, DomainEvent>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->events);
    }
}
