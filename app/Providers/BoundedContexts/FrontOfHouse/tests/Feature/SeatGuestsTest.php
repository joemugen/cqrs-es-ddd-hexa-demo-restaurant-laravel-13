<?php

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\FreeTable;
use App\Providers\BoundedContexts\FrontOfHouse\Application\FloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Table;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use Illuminate\Support\Facades\DB;

describe('seating guests', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
    });

    test('seating guests acknowledges the command and updates the floor plan', function () {
        $this->postJson('/api/front-of-house/tables/table-4/guests', [
            'guestCount' => 2,
        ])->assertOk()->assertJson([
            'tableId' => 'table-4',
            'accepted' => true,
        ])->assertJsonStructure(['visitId']);

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

        expect(DB::table(Tables::TABLES)->where('id', 'table-4')->value('guest_count'))->toBe(0)
            ->and(DB::table(Tables::FLOOR_PLAN)->where('table_id', 'table-4')->value('guest_count'))->toBe(0);
    });

    test('the floor plan query does not load the table aggregate', function () {
        $this->app->instance(TableRepository::class, new class implements TableRepository
        {
            public function get(TableId $id): Table
            {
                throw new RuntimeException('Queries must not hydrate aggregates.');
            }

            public function hosting(VisitId $visit): ?Table
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

    describe('a visit', function () {
        test('starts with the guests, is kept by the table until they leave, and is not shown by the floor plan', function () {
            $visit = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->json('visitId');

            expect(DB::table(Tables::TABLES)->where('id', 'table-4')->value('visit_id'))->toBe($visit);
        });

        test('seating again under the same key finds the same visit and does not seat twice', function () {
            $first = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2], ['Idempotency-Key' => 'welcome-1'])->assertOk()->json('visitId');
            $second = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2], ['Idempotency-Key' => 'welcome-1'])->assertOk()->json('visitId');

            expect($second)->toBe($first);
        });
    });

    describe('a visit that no key names', function () {
        test('is not the visit of the guests who sat before, even when the same key comes back after the keys were purged', function () {
            $first = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2], ['Idempotency-Key' => 'welcome-1'])->assertOk()->json('visitId');
            app(FrontOfHouseCommandBus::class)->dispatch(new FreeTable(new VisitId($first)));
            DB::table('idempotency_keys')->delete();

            $replayed = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2], ['Idempotency-Key' => 'welcome-1'])->assertOk()->json('visitId');

            expect($replayed)->not->toBe($first);
        });

        test('is not started twice by a replay without a key: the table already has its guests', function () {
            $first = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk()->json('visitId');

            $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertUnprocessable();

            expect(DB::table(Tables::TABLES)->where('id', 'table-4')->value('visit_id'))->toBe($first)
                ->and(DB::table(Tables::TABLES)->where('id', 'table-4')->value('visits_seated'))->toBe(1);
        });

        test('is the same for the same seating of a table, whichever way the guests came', function () {
            $first = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk()->json('visitId');

            expect($first)->toBe((string) VisitId::forSeating(new TableId('table-4'), 1));
        });
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
