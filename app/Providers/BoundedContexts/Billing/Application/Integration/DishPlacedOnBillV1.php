<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Integration;

use App\Providers\BoundedContexts\Billing\Domain\BillingEvent;
use App\Providers\BoundedContexts\Billing\Domain\Event\DishPlacedOnBill;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;

/**
 * A dish came out while the bill of its cover was open, as far as the ledger knows: the bill must receive it.
 */
#[PublishedAs(BillingEvent::DISH_PLACED_ON_BILL, version: 1)]
#[Translates(DishPlacedOnBill::class)]
final readonly class DishPlacedOnBillV1 implements IntegrationContract
{
    public function __construct(
        public string $coverRef,
        public string $billId,
        public string $sourceKey,
        public string $description,
        public int $quantity,
        public int $unitPriceCents,
        public string $kind,
    ) {}

    public static function from(DishPlacedOnBill $event): self
    {
        return new self(
            (string) $event->coverRef,
            (string) $event->billId,
            (string) $event->dish->sourceKey,
            $event->dish->description,
            $event->dish->quantity,
            $event->dish->unitPriceCents,
            $event->dish->kind,
        );
    }

    public function toPayload(): array
    {
        return [
            'coverRef' => $this->coverRef,
            'billId' => $this->billId,
            'sourceKey' => $this->sourceKey,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unitPriceCents' => $this->unitPriceCents,
            'kind' => $this->kind,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            (string) $payload['coverRef'],
            (string) $payload['billId'],
            (string) $payload['sourceKey'],
            (string) $payload['description'],
            (int) $payload['quantity'],
            (int) $payload['unitPriceCents'],
            (string) $payload['kind'],
        );
    }
}
