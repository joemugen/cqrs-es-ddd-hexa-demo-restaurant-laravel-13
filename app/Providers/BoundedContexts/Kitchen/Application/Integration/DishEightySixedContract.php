<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Integration;

use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishEightySixed;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenEvent;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;

/**
 * The kitchen ran out of this dish: the menu of the dining room must stop offering it.
 */
#[PublishedAs(KitchenEvent::DISH_EIGHTY_SIXED, version: 1)]
#[Translates(DishEightySixed::class)]
final readonly class DishEightySixedContract implements IntegrationContract
{
    public function __construct(public string $dishName) {}

    public static function from(DishEightySixed $event): self
    {
        return new self((string) $event->dishName);
    }

    public function toPayload(): array
    {
        return ['dishName' => $this->dishName];
    }

    public static function fromPayload(array $payload): static
    {
        return new self((string) $payload['dishName']);
    }
}
