<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;

describe('rendering a domain exception', function (): void {
    beforeEach(function (): void {
        $this->seed(FrontOfHouseSeeder::class);
    });

    it('answers with the message and the status of the exception', function (): void {
        $this->postJson('/api/front-of-house/tables/missing/guests', ['guestCount' => 2])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Unknown table missing.']);
    });

    it('renders a business refusal as a 422', function (): void {
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 0])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'Seat at least one guest.']);
    });

    it('refuses a malformed identifier with a 422 instead of looking it up', function (): void {
        $this->postJson('/api/front-of-house/tables/bad%20id/guests', ['guestCount' => 2])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'An identifier may only contain letters, digits, dashes and underscores.']);
    });
});
