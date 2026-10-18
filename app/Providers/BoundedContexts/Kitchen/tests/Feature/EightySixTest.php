<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;

describe('declaring a dish 86', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
    });

    test('declaring a dish 86 marks the menu unavailable and refuses a new order', function () {
        $this->postJson('/api/kitchen/dishes/dish-steak/eighty-six')
            ->assertExactJson(['dishId' => 'dish-steak', 'accepted' => true]);

        $steak = collect($this->getJson('/api/front-of-house/menu')->json())->firstWhere('menuItemId', 'steak-frites');

        expect($steak['available'])->toBeFalse();

        $this->postJson('/api/front-of-house/tables/table-4/orders', [
            'lines' => [
                ['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main'],
            ],
        ])->assertUnprocessable()
            ->assertJson(['message' => 'Steak frites is unavailable.']);
    });

    test('a dish already on a ticket can still be cooked after it is 86', function () {
        $this->postJson('/api/front-of-house/tables/table-4/orders', [
            'lines' => [
                ['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main'],
            ],
        ])->assertOk();

        $this->postJson('/api/kitchen/dishes/dish-steak/eighty-six')->assertOk();

        $screen = $this->getJson('/api/kitchen/screen')->json();
        $ticketId = $screen[0]['ticketId'];
        $lineId = $screen[0]['lines'][0]['lineId'];

        $this->postJson("/api/kitchen/tickets/{$ticketId}/fire", ['course' => 'main'])->assertOk();
        $this->postJson("/api/kitchen/tickets/{$ticketId}/lines/{$lineId}/ready")->assertOk();

        expect($this->getJson('/api/kitchen/screen')->json()[0]['done'])->toBeTrue();
    });

    test('a dish that is already 86 cannot be declared again', function () {
        $this->postJson('/api/kitchen/dishes/dish-steak/eighty-six')->assertOk();

        $this->postJson('/api/kitchen/dishes/dish-steak/eighty-six')
            ->assertUnprocessable()
            ->assertJson(['message' => 'Steak frites is already 86.']);
    });
});
