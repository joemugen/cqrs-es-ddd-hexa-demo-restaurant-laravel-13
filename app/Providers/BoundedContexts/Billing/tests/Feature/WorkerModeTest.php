<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;

describe('the till in worker mode', function () {
    beforeEach(function () {
        config(['consumption.mode' => 'worker']);
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
    });

    test('bills every dish served before the bill was asked for, even when all of it is waiting for one pass', function () {
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [
            ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
            ['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main'],
            ['menuItemId' => 'house-red', 'quantity' => 2, 'course' => 'drink'],
        ]])->assertOk();
        $this->artisan('kitchen:consume', ['--stop-when-empty' => true])->assertSuccessful();

        $ticket = $this->getJson('/api/kitchen/screen')->json()[0];
        foreach (['starter', 'main', 'drink'] as $course) {
            $this->postJson("/api/kitchen/tickets/{$ticket['ticketId']}/fire", ['course' => $course])->assertOk();
        }
        foreach ($ticket['lines'] as $line) {
            $this->postJson("/api/kitchen/tickets/{$ticket['ticketId']}/lines/{$line['lineId']}/ready")->assertOk();
        }
        $this->artisan('front-of-house:consume', ['--stop-when-empty' => true])->assertSuccessful();

        foreach ($this->getJson('/api/front-of-house/to-serve')->json() as $dish) {
            $this->postJson("/api/front-of-house/to-serve/{$dish['id']}/serve")->assertOk();
        }
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();

        // The three served dishes and the bill request are all still unread by the till.
        $this->artisan('billing:consume', ['--stop-when-empty' => true])->assertSuccessful();

        $this->getJson('/api/billing/covers/table-4/bill')
            ->assertOk()
            ->assertJsonPath('totalCents', 4500)
            ->assertJsonCount(3, 'lines');
    });
});
