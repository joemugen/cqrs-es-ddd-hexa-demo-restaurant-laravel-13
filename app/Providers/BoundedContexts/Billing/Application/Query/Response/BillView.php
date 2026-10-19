<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Query\Response;

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\Modules\SharedKernel\Application\QueryResponse;

final readonly class BillView implements QueryResponse
{
    /**
     * @param  list<BillLineView>  $lines
     * @param  list<PaymentView>  $payments
     */
    public function __construct(
        public BillId $billId,
        public CoverRef $coverRef,
        public string $status,
        public array $lines,
        public int $foodGrossCents,
        public int $alcoholGrossCents,
        public int $vatCents,
        public int $totalCents,
        public int $balanceCents,
        public int $tipCents,
        public array $payments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'billId' => (string) $this->billId,
            'coverRef' => (string) $this->coverRef,
            'status' => $this->status,
            'lines' => $this->lines,
            'foodGrossCents' => $this->foodGrossCents,
            'alcoholGrossCents' => $this->alcoholGrossCents,
            'vatCents' => $this->vatCents,
            'totalCents' => $this->totalCents,
            'balanceCents' => $this->balanceCents,
            'tipCents' => $this->tipCents,
            'payments' => $this->payments,
        ];
    }
}
