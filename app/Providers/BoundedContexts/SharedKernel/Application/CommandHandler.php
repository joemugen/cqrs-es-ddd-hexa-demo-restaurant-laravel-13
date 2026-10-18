<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application;

/**
 * Handles one command. It declares which with #[AsCommandHandler] and exposes a typed __invoke():
 *
 *     #[AsCommandHandler(SeatGuests::class)]
 *     final readonly class SeatGuestsHandler implements CommandHandler
 *     {
 *         public function __invoke(SeatGuests $command): TableSeated
 *     }
 *
 * PHP cannot put a parameter type on an interface method and narrow it, so the contract is the attribute:
 * the HandlerMap checks it against the signature when a bus is built.
 *
 * @template TCommand of Command
 * @template TResponse of CommandResponse
 */
interface CommandHandler {}
