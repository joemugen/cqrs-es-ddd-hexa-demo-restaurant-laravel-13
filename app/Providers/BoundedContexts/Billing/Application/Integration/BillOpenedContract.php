<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Integration;

use App\Providers\BoundedContexts\Billing\Domain\BillingEvent;
use App\Providers\BoundedContexts\Billing\Domain\Event\BillOpened;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;

/**
 * The ledger placed the dishes served so far on a new bill: the bill is opened with them. The till tells itself, since the
 * ledger and the bill are two consistency boundaries that agree through facts.
 */
#[PublishedAs(BillingEvent::BILL_OPENED, version: 1)]
#[Translates(BillOpened::class)]
final readonly class BillOpenedContract implements IntegrationContract
{
    /**
     * @param  list<array{sourceKey: string, description: string, quantity: int, unitPriceCents: int, kind: string}>  $dishes
     */
    public function __construct(
        public string $coverRef,
        public string $billId,
        public array $dishes,
    ) {}

    public static function from(BillOpened $event): self
    {
        return new self(
            (string) $event->coverRef,
            (string) $event->billId,
            array_map(fn (ServedDish $dish): array => [
                'sourceKey' => (string) $dish->sourceKey,
                'description' => $dish->description,
                'quantity' => $dish->quantity,
                'unitPriceCents' => $dish->unitPriceCents,
                'kind' => $dish->kind,
            ], $event->dishes),
        );
    }

    public function toPayload(): array
    {
        return [
            'coverRef' => $this->coverRef,
            'billId' => $this->billId,
            'dishes' => $this->dishes,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self((string) $payload['coverRef'], (string) $payload['billId'], array_values($payload['dishes']));
    }
}
