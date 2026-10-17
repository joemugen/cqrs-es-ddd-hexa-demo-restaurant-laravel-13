<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

use App\Providers\BoundedContexts\SharedKernel\Domain\AggregateRoot;

final class Bill implements AggregateRoot
{
    /**
     * @param  list<ServedDish>  $lines
     * @param  list<array{amountCents: int, tipCents: int}>  $payments
     */
    private function __construct(
        private readonly string $id,
        private readonly string $coverRef,
        private readonly array $lines,
        private array $payments,
        private bool $paid,
    ) {}

    /**
     * @param  list<ServedDish>  $lines
     */
    public static function prepare(string $id, string $coverRef, array $lines): self
    {
        if ($lines === []) {
            throw new BillingException('Nothing has been served.');
        }

        return new self($id, $coverRef, array_values($lines), [], false);
    }

    /**
     * @param  list<ServedDish>  $lines
     * @param  list<array{amountCents: int, tipCents: int}>  $payments
     */
    public static function restore(string $id, string $coverRef, array $lines, array $payments, bool $paid): self
    {
        return new self($id, $coverRef, $lines, $payments, $paid);
    }

    public function pay(int $amountCents, int $tipCents): void
    {
        if ($this->paid) {
            throw new BillingException('A paid bill cannot be changed.');
        }

        if ($amountCents < 1) {
            throw new BillingException('A payment needs an amount.');
        }

        if ($tipCents < 0) {
            throw new BillingException('A tip cannot be negative.');
        }

        if ($amountCents > $this->balanceCents()) {
            throw new BillingException('That payment is more than the balance.');
        }

        $this->payments[] = [
            'amountCents' => $amountCents,
            'tipCents' => $tipCents,
        ];

        if ($this->balanceCents() === 0) {
            $this->paid = true;
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function coverRef(): string
    {
        return $this->coverRef;
    }

    /**
     * @return list<ServedDish>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * @return list<array{amountCents: int, tipCents: int}>
     */
    public function payments(): array
    {
        return $this->payments;
    }

    public function isPaid(): bool
    {
        return $this->paid;
    }

    public function foodGrossCents(): int
    {
        return $this->grossOf('food');
    }

    public function alcoholGrossCents(): int
    {
        return $this->grossOf('alcohol');
    }

    public function vatCents(): int
    {
        return array_sum(array_map(fn (ServedDish $line): int => $line->vatCents(), $this->lines));
    }

    public function totalCents(): int
    {
        return array_sum(array_map(fn (ServedDish $line): int => $line->grossCents(), $this->lines));
    }

    public function balanceCents(): int
    {
        $settled = array_sum(array_column($this->payments, 'amountCents'));

        return $this->totalCents() - $settled;
    }

    public function tipCents(): int
    {
        return array_sum(array_column($this->payments, 'tipCents'));
    }

    private function grossOf(string $kind): int
    {
        $gross = 0;

        foreach ($this->lines as $line) {
            if ($line->kind === $kind) {
                $gross += $line->grossCents();
            }
        }

        return $gross;
    }
}
