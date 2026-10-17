<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

final class Table
{
    private function __construct(
        private readonly string $id,
        private readonly string $label,
        private int $guestCount,
    ) {}

    public static function restore(string $id, string $label, int $guestCount): self
    {
        return new self($id, $label, $guestCount);
    }

    public function seat(int $guestCount): void
    {
        if ($guestCount < 1) {
            throw new FrontOfHouseException('Seat at least one guest.');
        }

        if ($this->guestCount > 0) {
            throw new FrontOfHouseException('This table already has guests.');
        }

        $this->guestCount = $guestCount;
    }

    public function free(): void
    {
        $this->guestCount = 0;
    }

    public function hasGuests(): bool
    {
        return $this->guestCount > 0;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function guestCount(): int
    {
        return $this->guestCount;
    }

    public function status(): string
    {
        return $this->hasGuests() ? 'occupied' : 'free';
    }
}
