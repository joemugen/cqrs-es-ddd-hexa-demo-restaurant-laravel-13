<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Infrastructure;

use App\Providers\Modules\EventStore\Application\ConsumedMessages;
use App\Providers\Modules\EventStore\Domain\StoredEvent;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\ContextDiscovery;
use ReflectionClass;

/**
 * Finds the message of a context for a contract by the name and the version it declares with `#[Consumes]`. A contract
 * of a version the context does not read is not read at all.
 */
final class ConventionConsumedMessages implements ConsumedMessages
{
    /** @var array<string, array<string, class-string<ConsumedMessage>>> */
    private array $messagesByContext = [];

    public function __construct(private readonly ContextDiscovery $discovery) {}

    public function read(StoredEvent $event, string $consumer): ?ConsumedMessage
    {
        $message = $this->messagesOf($this->discovery->contextOf($consumer))[(string) $event->type] ?? null;

        if ($message === null || $this->versionOf($message) !== $event->contractVersion) {
            return null;
        }

        return $message::fromPayload($event->payload);
    }

    /**
     * @return array<string, class-string<ConsumedMessage>>
     */
    private function messagesOf(string $context): array
    {
        if (! isset($this->messagesByContext[$context])) {
            $this->messagesByContext[$context] = [];

            foreach ($this->discovery->consumedMessages($context) as $message) {
                $this->messagesByContext[$context][$this->consumes($message)->type] = $message;
            }
        }

        return $this->messagesByContext[$context];
    }

    /**
     * @param  class-string<ConsumedMessage>  $message
     */
    private function versionOf(string $message): int
    {
        return $this->consumes($message)->version;
    }

    /**
     * @param  class-string<ConsumedMessage>  $message
     */
    private function consumes(string $message): Consumes
    {
        return (new ReflectionClass($message))->getAttributes(Consumes::class)[0]->newInstance();
    }
}
