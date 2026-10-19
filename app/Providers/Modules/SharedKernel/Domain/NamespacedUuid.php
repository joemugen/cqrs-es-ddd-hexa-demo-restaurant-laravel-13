<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

/**
 * Name-based UUIDs (version 5, RFC 4122): the same namespace and name always give the same UUID.
 *
 * Each aggregate type has its own namespace, derived from the application's, so a bill id can never equal
 * an order id. Deriving the name from the business fact, such as the cover and the position of the event
 * that asked for a bill, makes creating the aggregate idempotent: doing it twice finds the first one.
 */
final class NamespacedUuid
{
    /** uuid v5 of the DNS namespace and "cqrs-restaurant-post-demo-laravel.joemugen" */
    public const APPLICATION = '0205e551-9867-586f-a677-c64d877c60a3';

    public static function generate(string $aggregate, string $name): string
    {
        return self::v5(self::v5(self::APPLICATION, $aggregate), $name);
    }

    public static function v5(string $namespace, string $name): string
    {
        $bytes = substr(sha1((string) hex2bin(str_replace('-', '', $namespace)).$name, true), 0, 16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x50);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }
}
