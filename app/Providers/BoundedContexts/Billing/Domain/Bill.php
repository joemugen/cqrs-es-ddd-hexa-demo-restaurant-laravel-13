<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

use App\Providers\BoundedContexts\Billing\Domain\Event\BillPaid;
use App\Providers\BoundedContexts\Billing\Domain\Event\DishWrittenOff;
use App\Providers\Modules\SharedKernel\Domain\AggregateRoot;
use App\Providers\Modules\SharedKernel\Domain\RecordsEvents;

final class Bill implements AggregateRoot
{
    use RecordsEvents;

    private readonly int $revisionWhenLoaded;

    /**
     * @param  list<ServedDish>  $lines
     * @param  list<Payment>  $payments
     */
    private function __construct(
        private readonly BillId $id,
        private readonly CoverRef $coverRef,
        private array $lines,
        private array $payments,
        private bool $paid,
        private int $version,
        private int $revision,
    ) {
        $this->revisionWhenLoaded = $revision;
    }

    /**
     * @param  list<ServedDish>  $lines
     */
    public static function prepare(BillId $id, CoverRef $coverRef, array $lines): self
    {
        if ($lines === []) {
            throw new BillingException('Nothing has been served.');
        }

        return new self($id, $coverRef, array_values($lines), [], false, 1, 0);
    }

    /**
     * @param  list<ServedDish>  $lines
     * @param  list<Payment>  $payments
     */
    public static function restore(BillId $id, CoverRef $coverRef, array $lines, array $payments, bool $paid, int $version = 1, int $revision = 0): self
    {
        return new self($id, $coverRef, $lines, $payments, $paid, $version, $revision);
    }

    /**
     * Asking for the bill freezes nothing: a dish served afterwards goes on the bill that is still open, which moves to its
     * next version. A dish told twice is on the bill once. Once the bill is paid it is immutable: a dish that comes out
     * after that is written off, and the loss is a fact.
     */
    public function receive(ServedDish $dish): void
    {
        if ($this->paid) {
            $this->recordThat(new DishWrittenOff($this->id, $this->coverRef, $dish->sourceKey, $dish->description, $dish->quantity, $dish->grossCents()));

            return;
        }

        foreach ($this->lines as $line) {
            if ($line->sourceKey->equals($dish->sourceKey)) {
                return;
            }
        }

        $this->lines[] = $dish;
        $this->version++;
        $this->revision++;
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

        $this->payments[] = new Payment($amountCents, $tipCents);
        $this->revision++;

        if ($this->balanceCents() === 0) {
            $this->paid = true;
            $this->recordThat(new BillPaid($this->id, $this->coverRef));
        }
    }

    public function id(): BillId
    {
        return $this->id;
    }

    public function coverRef(): CoverRef
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
     * @return list<Payment>
     */
    public function payments(): array
    {
        return $this->payments;
    }

    /**
     * Which note the guests saw: it grows each time a dish is added to the lines.
     */
    public function version(): int
    {
        return $this->version;
    }

    /**
     * How many times the bill was written, a payment as much as a dish: what a save checks so that two writers who read the same
     * bill cannot both write it. It is not the version of the note, which a payment does not change.
     */
    public function revisionWhenLoaded(): int
    {
        return $this->revisionWhenLoaded;
    }

    public function revision(): int
    {
        return $this->revision;
    }

    public function isPaid(): bool
    {
        return $this->paid;
    }

    public function foodGrossCents(): int
    {
        return $this->grossOf(DishKind::FOOD);
    }

    public function alcoholGrossCents(): int
    {
        return $this->grossOf(DishKind::ALCOHOL);
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
        $settled = array_sum(array_map(fn (Payment $payment): int => $payment->amountCents, $this->payments));

        return $this->totalCents() - $settled;
    }

    public function tipCents(): int
    {
        return array_sum(array_map(fn (Payment $payment): int => $payment->tipCents, $this->payments));
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
