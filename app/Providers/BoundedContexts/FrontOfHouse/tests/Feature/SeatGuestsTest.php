<?php

use App\Providers\BoundedContexts\FrontOfHouse\Application\FloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Table;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use Illuminate\Support\Facades\DB;

describe('seating guests', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
    });

    test('seating guests acknowledges the command and updates the floor plan', function () {
        $this->postJson('/api/front-of-house/tables/table-4/guests', [
            'guestCount' => 2,
        ])->assertOk()->assertExactJson([
            'tableId' => 'table-4',
            'accepted' => true,
        ]);

        $this->getJson('/api/front-of-house/floor-plan')
            ->assertOk()
            ->assertExactJson([
                [
                    'tableId' => 'table-4',
                    'label' => 'Window 4',
                    'guestCount' => 2,
                    'status' => 'occupied',
                ],
                [
                    'tableId' => 'table-7',
                    'label' => 'Terrace 7',
                    'guestCount' => 0,
                    'status' => 'free',
                ],
            ]);
    });

    test('a floor plan failure rolls the seating back', function () {
        $this->app->instance(FloorPlan::class, new class implements FloorPlan
        {
            public function sync(Table $table): void
            {
                throw new RuntimeException('The floor plan could not be written.');
            }
        });

        $this->postJson('/api/front-of-house/tables/table-4/guests', [
            'guestCount' => 2,
        ])->assertStatus(500);

        expect(DB::table('foh_tables')->where('id', 'table-4')->value('guest_count'))->toBe(0)
            ->and(DB::table('foh_floor_plan')->where('table_id', 'table-4')->value('guest_count'))->toBe(0);
    });

    test('the floor plan query does not load the table aggregate', function () {
        $this->app->instance(TableRepository::class, new class implements TableRepository
        {
            public function get(TableId $id): Table
            {
                throw new RuntimeException('Queries must not hydrate aggregates.');
            }

            public function save(Table $table): void
            {
                throw new RuntimeException('Queries must not hydrate aggregates.');
            }
        });

        $this->getJson('/api/front-of-house/floor-plan')->assertOk();
    });

    describe('refusals', function () {
        test('a table without a guest count is a malformed command', function () {
            $this->postJson('/api/front-of-house/tables/table-4/guests', [])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['guestCount']);
        });

        test('seating nobody is a business refusal', function () {
            $this->postJson('/api/front-of-house/tables/table-4/guests', [
                'guestCount' => 0,
            ])->assertUnprocessable()->assertJson([
                'message' => 'Seat at least one guest.',
            ]);
        });

        test('a second seating is refused', function () {
            $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();

            $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 4])
                ->assertUnprocessable()
                ->assertJson(['message' => 'This table already has guests.']);
        });

        test('an unknown table is not found', function () {
            $this->postJson('/api/front-of-house/tables/missing/guests', ['guestCount' => 2])
                ->assertNotFound()
                ->assertJson(['message' => 'Unknown table missing.']);
        });
    });
});
