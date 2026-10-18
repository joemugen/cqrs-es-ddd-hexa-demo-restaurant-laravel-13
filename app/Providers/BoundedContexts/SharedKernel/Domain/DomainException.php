<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Domain;

use RuntimeException;
use Stringable;

/**
 * A business refusal. The message is an English template whose :placeholders are filled from $replace,
 * and it doubles as the translation key: the HTTP edge translates it, the domain never does.
 */
abstract class DomainException extends RuntimeException
{
    /** @var array<string, string> */
    private readonly array $replace;

    /**
     * @param  array<string, string|int|Stringable>  $replace
     */
    public function __construct(
        private readonly string $translationKey,
        private readonly int $status = 422,
        array $replace = [],
    ) {
        $this->replace = array_map(fn (string|int|Stringable $value): string => (string) $value, $replace);

        parent::__construct(self::fill($translationKey, $this->replace));
    }

    public function status(): int
    {
        return $this->status;
    }

    public function translationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * @return array<string, string>
     */
    public function replacements(): array
    {
        return $this->replace;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function fill(string $template, array $replace): string
    {
        $pairs = [];

        foreach ($replace as $name => $value) {
            $pairs[':'.$name] = $value;
        }

        return strtr($template, $pairs);
    }
}
