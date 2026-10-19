<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Bus;

use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsQueryHandler;
use App\Providers\Modules\SharedKernel\Application\Bus\Exception\HandlerMismatch;
use App\Providers\Modules\SharedKernel\Application\Bus\Exception\NoHandlerRegistered;
use App\Providers\Modules\SharedKernel\Application\Command;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;
use App\Providers\Modules\SharedKernel\Application\Query;
use App\Providers\Modules\SharedKernel\Application\QueryHandler;
use App\Providers\Modules\SharedKernel\Application\QueryResponse;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Which handler answers which message. Building it checks every handler against its contract,
 * so a wrong type is a boot error and not a surprise at the first request.
 */
final readonly class HandlerMap
{
    /**
     * @param  array<class-string, class-string>  $handlers  message => handler
     */
    private function __construct(private array $handlers) {}

    /**
     * @param  list<class-string>  $handlerClasses
     */
    public static function forCommands(array $handlerClasses): self
    {
        return self::build($handlerClasses, AsCommandHandler::class, CommandHandler::class, Command::class, CommandResponse::class, true);
    }

    /**
     * @param  list<class-string>  $handlerClasses
     */
    public static function forQueries(array $handlerClasses): self
    {
        return self::build($handlerClasses, AsQueryHandler::class, QueryHandler::class, Query::class, QueryResponse::class, false);
    }

    /**
     * @return class-string
     */
    public function handlerFor(object $message): string
    {
        return $this->handlers[$message::class] ?? throw NoHandlerRegistered::for($message);
    }

    /**
     * @return array<class-string, class-string>
     */
    public function handlers(): array
    {
        return $this->handlers;
    }

    /**
     * @param  list<class-string>  $handlerClasses
     * @param  class-string  $attribute
     * @param  class-string  $contract
     * @param  class-string  $messageType
     * @param  class-string  $responseType
     */
    private static function build(
        array $handlerClasses,
        string $attribute,
        string $contract,
        string $messageType,
        string $responseType,
        bool $mayAnswerNothing,
    ): self {
        $handlers = [];

        foreach ($handlerClasses as $class) {
            $message = self::declaredMessage($class, $attribute, $contract, $messageType);
            self::checkInvoke($class, $message, $responseType, $mayAnswerNothing);

            if (isset($handlers[$message])) {
                throw new HandlerMismatch("{$message} is already handled by {$handlers[$message]}, so {$class} cannot handle it too.");
            }

            $handlers[$message] = $class;
        }

        return new self($handlers);
    }

    /**
     * @param  class-string  $class
     * @param  class-string  $attribute
     * @param  class-string  $contract
     * @param  class-string  $messageType
     * @return class-string
     */
    private static function declaredMessage(string $class, string $attribute, string $contract, string $messageType): string
    {
        $attributes = (new ReflectionClass($class))->getAttributes($attribute);

        if ($attributes === []) {
            throw new HandlerMismatch("{$class} must declare #[".self::short($attribute).'].');
        }

        if (! is_subclass_of($class, $contract)) {
            throw new HandlerMismatch("{$class} must implement ".self::short($contract).'.');
        }

        $message = $attributes[0]->newInstance()->message;

        if (! is_subclass_of($message, $messageType)) {
            throw new HandlerMismatch("{$message} declared by {$class} must implement ".self::short($messageType).'.');
        }

        return $message;
    }

    /**
     * @param  class-string  $class
     * @param  class-string  $message
     * @param  class-string  $responseType
     */
    private static function checkInvoke(string $class, string $message, string $responseType, bool $mayAnswerNothing): void
    {
        $reflection = new ReflectionClass($class);

        if (! $reflection->hasMethod('__invoke') || ! $reflection->getMethod('__invoke')->isPublic()) {
            throw new HandlerMismatch("{$class} must have a public __invoke().");
        }

        $invoke = $reflection->getMethod('__invoke');
        $parameter = $invoke->getParameters()[0] ?? null;
        $type = $parameter?->getType();

        if (! $type instanceof ReflectionNamedType || $type->getName() !== $message || $type->allowsNull()) {
            throw new HandlerMismatch("{$class}::__invoke() must accept ".self::short($message).' as its only parameter.');
        }

        $return = $invoke->getReturnType();

        if (
            ! $return instanceof ReflectionNamedType
            || ! is_a($return->getName(), $responseType, true)
            || ($return->allowsNull() && ! $mayAnswerNothing)
        ) {
            throw new HandlerMismatch("{$class}::__invoke() must return a ".self::short($responseType).($mayAnswerNothing ? ' or null' : '').'.');
        }
    }

    private static function short(string $class): string
    {
        return substr(strrchr('\\'.$class, '\\'), 1);
    }
}
