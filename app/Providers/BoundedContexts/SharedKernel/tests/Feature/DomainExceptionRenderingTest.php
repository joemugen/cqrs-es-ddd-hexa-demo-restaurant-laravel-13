<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;

describe('rendering a domain exception', function (): void {
    beforeEach(function (): void {
        $this->seed(FrontOfHouseSeeder::class);
    });

    it('answers in English by default, with the status of the exception', function (): void {
        $this->postJson('/api/front-of-house/tables/missing/guests', ['guestCount' => 2])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Unknown table missing.']);
    });

    it('translates the message into the locale of the request', function (): void {
        app()->setLocale('fr');

        $this->postJson('/api/front-of-house/tables/missing/guests', ['guestCount' => 2])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Table inconnue missing.']);
    });

    it('translates a message that carries no placeholder', function (): void {
        app()->setLocale('fr');

        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 0])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'Installez au moins un convive.']);
    });
});
