<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Query\Response;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\UnavailableLine;
use App\Providers\Modules\SharedKernel\Application\QueryResponse;

final readonly class OrderFulfilmentView implements QueryResponse
{
    /**
     * @param  list<UnavailableLine>  $unavailableLines
     */
    public function __construct(
        public TableOrderId $tableOrderId,
        public TableId $tableId,
        public string $status,
        public ?string $reason,
        public array $unavailableLines,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'tableOrderId' => (string) $this->tableOrderId,
            'tableId' => (string) $this->tableId,
            'status' => $this->status,
            'reason' => $this->reason,
            'unavailableLines' => array_map(fn (UnavailableLine $line): array => $line->toPayload(), $this->unavailableLines),
        ];
    }
}
