<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Integration;

use App\Providers\BoundedContexts\Billing\Domain\BillingEvent;
use App\Providers\BoundedContexts\Billing\Domain\Event\DishWrittenOff;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;

/**
 * A dish was served after the bill of its cover was paid: the till writes it off, and says how much was lost.
 */
#[PublishedAs(BillingEvent::DISH_WRITTEN_OFF, version: 1)]
#[Translates(DishWrittenOff::class)]
final readonly class DishWrittenOffV1 implements IntegrationContract
{
    public function __construct(
        public string $coverRef,
        public string $billId,
        public string $sourceKey,
        public string $description,
        public int $quantity,
        public int $amountCents,
    ) {}

    public static function from(DishWrittenOff $event): self
    {
        return new self(
            (string) $event->coverRef,
            (string) $event->billId,
            (string) $event->sourceKey,
            $event->description,
            $event->quantity,
            $event->amountCents,
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
            'amountCents' => $this->amountCents,
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
            (int) $payload['amountCents'],
        );
    }
}
