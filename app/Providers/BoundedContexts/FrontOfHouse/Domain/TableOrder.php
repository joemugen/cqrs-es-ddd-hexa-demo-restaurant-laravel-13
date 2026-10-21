<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\TableOrderPlaced;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\TableOrderRevised;
use App\Providers\Modules\SharedKernel\Domain\AggregateRoot;
use App\Providers\Modules\SharedKernel\Domain\RecordsEvents;

final class TableOrder implements AggregateRoot
{
    use RecordsEvents;

    /**
     * @param  list<OrderLine>  $lines
     */
    private function __construct(
        private readonly TableOrderId $id,
        private readonly TableId $tableId,
        private array $lines,
    ) {}

    /**
     * @param  list<OrderLine>  $lines
     */
    public static function restore(TableOrderId $id, TableId $tableId, array $lines): self
    {
        return new self($id, $tableId, $lines);
    }

    /**
     * @param  list<RequestedLine>  $requested
     */
    public static function place(TableOrderId $id, Table $table, Menu $menu, array $requested): self
    {
        if (! $table->hasGuests()) {
            throw new FrontOfHouseException('No order for a table without seated guests.');
        }

        if ($requested === []) {
            throw new FrontOfHouseException('An order needs at least one item.');
        }

        $order = new self($id, $table->id(), self::linesOf($id, 0, $menu, $requested));
        $order->recordThat(new TableOrderPlaced($id, $table->id(), $table->label(), $order->lines));

        return $order;
    }

    /**
     * The waiter replaces what is unavailable: the new lines go on after the lines already placed.
     *
     * @param  list<RequestedLine>  $requested
     */
    public function revise(Table $table, Menu $menu, array $requested): void
    {
        if ($requested === []) {
            throw new FrontOfHouseException('A revision needs at least one item.');
        }

        $added = self::linesOf($this->id, count($this->lines), $menu, $requested);
        $this->lines = [...$this->lines, ...$added];
        $this->recordThat(new TableOrderRevised($this->id, $this->tableId, $table->label(), $added));
    }

    public function id(): TableOrderId
    {
        return $this->id;
    }

    public function tableId(): TableId
    {
        return $this->tableId;
    }

    /**
     * @return list<OrderLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * What the kitchen needs. The price stays on the menu.
     *
     * @return list<array{lineId: string, name: string, quantity: int, course: string, allergens: list<string>}>
     */
    public function forTheKitchen(): array
    {
        return array_map(fn (OrderLine $line): array => $line->forTheKitchen(), $this->lines);
    }

    /**
     * @param  list<RequestedLine>  $requested
     * @return list<OrderLine>
     */
    private static function linesOf(TableOrderId $id, int $alreadyPlaced, Menu $menu, array $requested): array
    {
        $lines = [];

        foreach ($requested as $index => $line) {
            if ($line->quantity < 1) {
                throw new FrontOfHouseException('Each line needs a quantity of at least one.');
            }

            if (! in_array($line->course, Course::ALL, true)) {
                throw new FrontOfHouseException('Each line needs a course: starter, main, dessert, or drink.');
            }

            $item = $menu->get($line->menuItemId);

            if (! $item->isAvailable()) {
                throw new FrontOfHouseException("{$item->name()} is unavailable.");
            }

            $lines[] = new OrderLine(
                new LineId($id.'-'.($alreadyPlaced + $index + 1)),
                $item->id(),
                $item->name(),
                $line->quantity,
                $line->course,
                $item->priceCents(),
                $item->kind(),
                $item->allergens(),
            );
        }

        return $lines;
    }
}
