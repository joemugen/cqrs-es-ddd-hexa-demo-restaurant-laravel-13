<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

/**
 * The version of a resource as the client read it, sent back as an If-Match header: the command is only valid if
 * nothing happened to the resource since.
 */
final readonly class ExpectedVersion
{
    private function __construct(public int $value) {}

    public static function of(int $version): self
    {
        return new self($version);
    }

    /**
     * No header, or *, means the client did not ask for a check.
     *
     * @throws InvalidValue
     */
    public static function fromIfMatch(?string $header): ?self
    {
        $header = trim((string) $header);

        if ($header === '' || $header === '*') {
            return null;
        }

        if (preg_match('/^(?:W\/)?"?(\d+)"?$/D', $header, $match) !== 1) {
            throw new InvalidValue('If-Match must be the version of the resource you read, such as "3".');
        }

        return new self((int) $match[1]);
    }

    /**
     * @throws PreconditionFailed
     */
    public function ensureMatches(int $actual): void
    {
        if ($actual !== $this->value) {
            throw new PreconditionFailed("The resource changed since you read it: it is at version {$actual}, not {$this->value}.");
        }
    }
}
