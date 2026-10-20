<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\Modules\EventStore\Domain\EventStore;

describe('a kitchen ticket version', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [
            ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
            ['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main'],
        ]])->assertOk();
        $ticket = $this->getJson('/api/kitchen/screen')->json()[0];
        $this->ticketId = $ticket['ticketId'];
        $this->salad = collect($ticket['lines'])->firstWhere('dishName', 'Green salad')['lineId'];
        $this->screenVersion = fn () => $this->getJson('/api/kitchen/screen')->json()[0]['version'];
        $this->eventCount = fn () => count(app(EventStore::class)->readAllFrom(0));
    });

    describe('on the screen', function () {
        test('is the number of facts of the ticket, so a client can say which state it read', function () {
            expect(($this->screenVersion)())->toBe(1);

            $this->postJson("/api/kitchen/tickets/{$this->ticketId}/fire", ['course' => 'starter'])->assertOk();
            expect(($this->screenVersion)())->toBe(2);

            $this->postJson("/api/kitchen/tickets/{$this->ticketId}/lines/{$this->salad}/ready")->assertOk();
            expect(($this->screenVersion)())->toBe(3);
        });
    });

    describe('sent back as If-Match', function () {
        test('lets a command through when the ticket is still as the client read it, and answers with the new version', function () {
            $this->postJson("/api/kitchen/tickets/{$this->ticketId}/fire", ['course' => 'starter'], ['If-Match' => '"1"'])
                ->assertOk()
                ->assertHeader('ETag', '"2"')
                ->assertExactJson(['kitchenTicketId' => $this->ticketId, 'accepted' => true]);

            $this->postJson("/api/kitchen/tickets/{$this->ticketId}/lines/{$this->salad}/ready", [], ['If-Match' => '"2"'])
                ->assertOk()
                ->assertHeader('ETag', '"3"');
        });

        test('refuses with a 412 a command decided on an older state, and changes nothing', function () {
            $this->postJson("/api/kitchen/tickets/{$this->ticketId}/fire", ['course' => 'starter'])->assertOk();
            $before = ($this->eventCount)();

            $this->postJson("/api/kitchen/tickets/{$this->ticketId}/fire", ['course' => 'main'], ['If-Match' => '"1"'])
                ->assertStatus(412)
                ->assertExactJson(['message' => 'The resource changed since you read it: it is at version 2, not 1.']);

            $this->postJson("/api/kitchen/tickets/{$this->ticketId}/lines/{$this->salad}/ready", [], ['If-Match' => '"1"'])
                ->assertStatus(412);

            expect(($this->eventCount)())->toBe($before);
        });

        test('is optional: without it, or with *, the command is not checked', function () {
            $this->postJson("/api/kitchen/tickets/{$this->ticketId}/fire", ['course' => 'starter'])->assertOk();
            $this->postJson("/api/kitchen/tickets/{$this->ticketId}/lines/{$this->salad}/ready", [], ['If-Match' => '*'])->assertOk();
        });

        test('accepts a weak validator', function () {
            $this->postJson("/api/kitchen/tickets/{$this->ticketId}/fire", ['course' => 'starter'], ['If-Match' => 'W/"1"'])->assertOk();
        });

        test('is refused with a 422 when it is not a version', function () {
            $this->postJson("/api/kitchen/tickets/{$this->ticketId}/fire", ['course' => 'starter'], ['If-Match' => 'banana'])
                ->assertUnprocessable()
                ->assertExactJson(['message' => 'If-Match must be the version of the resource you read, such as "3".']);
        });

        test('is not replayed by the bus: a stale command is a refusal, not a conflict to retry', function () {
            $this->postJson("/api/kitchen/tickets/{$this->ticketId}/fire", ['course' => 'starter'])->assertOk();

            $this->postJson("/api/kitchen/tickets/{$this->ticketId}/fire", ['course' => 'main'], ['If-Match' => '"1"'])
                ->assertStatus(412);

            expect(($this->screenVersion)())->toBe(2);
        });
    });
});
