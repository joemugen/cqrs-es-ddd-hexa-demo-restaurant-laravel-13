<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Query\Response;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\TableLabel;
use JsonSerializable;

final readonly class KitchenScreenTicket implements JsonSerializable
{
    /**
     * @param  list<KitchenScreenLine>  $lines
     */
    public function __construct(
        public KitchenTicketId $ticketId,
        public PartyRef $partyRef,
        public TableLabel $tableLabel,
        public bool $done,
        public int $version,
        public array $lines,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'ticketId' => (string) $this->ticketId,
            'partyRef' => (string) $this->partyRef,
            'tableLabel' => (string) $this->tableLabel,
            'done' => $this->done,
            'version' => $this->version,
            'lines' => $this->lines,
        ];
    }
}
