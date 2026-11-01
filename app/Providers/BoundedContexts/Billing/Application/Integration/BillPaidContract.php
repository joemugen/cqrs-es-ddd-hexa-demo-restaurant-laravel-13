<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Integration;

use App\Providers\BoundedContexts\Billing\Domain\BillingEvent;
use App\Providers\BoundedContexts\Billing\Domain\Event\BillPaid;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;

/**
 * The bill of this cover is paid: the dining room frees the table.
 */
#[PublishedAs(BillingEvent::BILL_PAID, version: 1)]
#[Translates(BillPaid::class)]
final readonly class BillPaidContract implements IntegrationContract
{
    public function __construct(
        public string $coverRef,
        public string $billId,
    ) {}

    public static function from(BillPaid $event): self
    {
        return new self((string) $event->coverRef, (string) $event->billId);
    }

    public function toPayload(): array
    {
        return [
            'coverRef' => $this->coverRef,
            'billId' => $this->billId,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self((string) $payload['coverRef'], (string) $payload['billId']);
    }
}
