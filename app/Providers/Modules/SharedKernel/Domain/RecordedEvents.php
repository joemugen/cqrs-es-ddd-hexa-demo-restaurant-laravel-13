<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * An ordered, immutable run of facts: the history of an aggregate or what it has yet to commit.
 *
 * @implements IteratorAggregate<int, RecordedEvent>
 */
final readonly class RecordedEvents implements Countable, IteratorAggregate
{
    /**
     * @param  list<RecordedEvent>  $events
     */
    public function __construct(private array $events = []) {}

    public static function of(RecordedEvent ...$events): self
    {
        return new self(array_values($events));
    }

    public function with(RecordedEvent $event): self
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
     * @return Traversable<int, RecordedEvent>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->events);
    }
}
