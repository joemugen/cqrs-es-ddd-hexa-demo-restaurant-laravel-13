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

    it('refuses a malformed identifier with a 422 instead of looking it up', function (): void {
        $this->postJson('/api/front-of-house/tables/bad%20id/guests', ['guestCount' => 2])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'An identifier may only contain letters, digits, dashes and underscores.']);
    });

    it('translates a value rule that carries a placeholder', function (): void {
        app()->setLocale('fr');

        $this->postJson('/api/front-of-house/tables/'.str_repeat('a', 65).'/guests', ['guestCount' => 2])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'Un identifiant ne peut pas dépasser 64 caractères.']);
    });
});
