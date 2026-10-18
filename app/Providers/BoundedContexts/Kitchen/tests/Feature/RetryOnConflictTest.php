<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicket;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\BoundedContexts\SharedKernel\Domain\ConcurrencyConflict;

/**
 * A repository that conflicts on its first $conflicts saves, then behaves.
 */
function conflictingTickets(KitchenTicketRepository $real, int $conflicts): KitchenTicketRepository
{
    return new class($real, $conflicts) implements KitchenTicketRepository
    {
        public int $saves = 0;

        public function __construct(private KitchenTicketRepository $real, private int $conflicts) {}

        public function exists(KitchenTicketId $id): bool
        {
            return $this->real->exists($id);
        }

        public function get(KitchenTicketId $id): KitchenTicket
        {
            return $this->real->get($id);
        }

        public function save(KitchenTicket $ticket): void
        {
            $this->saves++;

            if ($this->saves <= $this->conflicts) {
                throw new ConcurrencyConflict('Stream kitchen-ticket-1 is at version 3, expected 2.');
            }

            $this->real->save($ticket);
        }
    };
}

describe('firing a course while another cook changes the same ticket', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->postJson('/api/front-of-house/tables/table-4/orders', [
            'lines' => [['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter']],
        ])->assertOk();
        $this->ticketId = $this->getJson('/api/kitchen/screen')->json()[0]['ticketId'];
    });

    test('the kitchen bus replays the command and the fire goes through', function () {
        $tickets = conflictingTickets(app(KitchenTicketRepository::class), conflicts: 2);
        $this->app->instance(KitchenTicketRepository::class, $tickets);

        $this->postJson("/api/kitchen/tickets/{$this->ticketId}/fire", ['course' => 'starter'])
            ->assertOk()
            ->assertExactJson(['kitchenTicketId' => $this->ticketId, 'accepted' => true]);

        expect($tickets->saves)->toBe(3)
            ->and($this->getJson('/api/kitchen/screen')->json()[0]['lines'][0]['status'])->toBe('fired');
    });

    test('after the last attempt the caller gets a 409', function () {
        $tickets = conflictingTickets(app(KitchenTicketRepository::class), conflicts: 10);
        $this->app->instance(KitchenTicketRepository::class, $tickets);

        $this->postJson("/api/kitchen/tickets/{$this->ticketId}/fire", ['course' => 'starter'])
            ->assertStatus(409);

        expect($tickets->saves)->toBe(3);
    });
});
