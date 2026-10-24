<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

use App\Providers\Modules\SharedKernel\Domain\StringValue;

/**
 * Where a served dish came from: the order and the line, so a dish is charged once.
 */
final readonly class SourceKey extends StringValue
{
    private const MAX_LENGTH = 129;

    public static function ofLine(string $tableOrderId, string $lineId): self
    {
        return new self($tableOrderId.':'.$lineId);
    }

    protected static function validate(string $value): void
    {
        self::requireFilled($value, 'A source key cannot be blank.');
        self::requireAtMost($value, self::MAX_LENGTH, 'A source key');
        self::requireMatching(
            $value,
            '/^[A-Za-z0-9_-]+:[A-Za-z0-9_-]+$/D',
            'A source key is an order and a line joined by a colon.',
        );
    }
}
