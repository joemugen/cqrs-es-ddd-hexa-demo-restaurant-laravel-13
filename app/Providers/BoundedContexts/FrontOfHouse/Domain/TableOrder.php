<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

final readonly class TableOrder
{
    /**
     * @param  list<array{lineId: string, menuItemId: string, name: string, quantity: int, course: string, unitPriceCents: int, kind: string, allergens: list<string>}>  $lines
     */
    private function __construct(
        private string $id,
        private string $tableId,
        private array $lines,
    ) {}

    /**
     * @param  list<array{menuItemId: string, quantity: int, course: string}>  $requested
     */
    public static function place(string $id, Table $table, Menu $menu, array $requested): self
    {
        if (! $table->hasGuests()) {
            throw new FrontOfHouseException('No order for a table without seated guests.');
        }

        if ($requested === []) {
            throw new FrontOfHouseException('An order needs at least one item.');
        }

        $lines = [];

        foreach ($requested as $index => $line) {
            if ($line['quantity'] < 1) {
                throw new FrontOfHouseException('Each line needs a quantity of at least one.');
            }

            if (! in_array($line['course'], ['starter', 'main', 'dessert', 'drink'], true)) {
                throw new FrontOfHouseException('Each line needs a course: starter, main, dessert, or drink.');
            }

            $item = $menu->get($line['menuItemId']);

            if (! $item->isAvailable()) {
                throw new FrontOfHouseException("{$item->name()} is unavailable.");
            }

            $lines[] = [
                'lineId' => $id.'-'.($index + 1),
                'menuItemId' => $item->id(),
                'name' => $item->name(),
                'quantity' => $line['quantity'],
                'course' => $line['course'],
                'unitPriceCents' => $item->priceCents(),
                'kind' => $item->kind(),
                'allergens' => $item->allergens(),
            ];
        }

        return new self($id, $table->id(), $lines);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function tableId(): string
    {
        return $this->tableId;
    }

    /**
     * @return list<array{lineId: string, menuItemId: string, name: string, quantity: int, course: string, unitPriceCents: int, kind: string, allergens: list<string>}>
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
        return array_map(fn (array $line): array => [
            'lineId' => $line['lineId'],
            'name' => $line['name'],
            'quantity' => $line['quantity'],
            'course' => $line['course'],
            'allergens' => $line['allergens'],
        ], $this->lines);
    }
}
