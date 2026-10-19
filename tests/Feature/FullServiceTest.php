<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use Illuminate\Support\Facades\DB;

test('a service runs from the welcome to the departure', function () {
    config(['queue.default' => 'database']);
    $this->seed(FrontOfHouseSeeder::class);
    $this->seed(KitchenSeeder::class);

    $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])
        ->assertExactJson(['tableId' => 'table-4', 'accepted' => true]);

    $seated = collect($this->getJson('/api/front-of-house/floor-plan')->json())->firstWhere('tableId', 'table-4');
    expect($seated)->toMatchArray(['guestCount' => 2, 'status' => 'occupied', 'label' => 'Window 4']);

    $this->postJson('/api/front-of-house/tables/table-4/orders', [
        'lines' => [
            ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
            ['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main'],
            ['menuItemId' => 'house-red', 'quantity' => 2, 'course' => 'drink'],
        ],
    ])->assertJson(['accepted' => true]);

    $first = $this->getJson('/api/kitchen/screen')->assertOk()->json()[0];
    expect($first['lines'])->toHaveCount(3)
        ->and($first['lines'][0])->not->toHaveKey('priceCents')
        ->and($first['partyRef'])->toBe('table-4');

    $lines = collect($first['lines'])->keyBy('dishName');
    $this->postJson("/api/kitchen/tickets/{$first['ticketId']}/fire", ['course' => 'starter'])->assertOk();
    $this->postJson("/api/kitchen/tickets/{$first['ticketId']}/lines/{$lines['Green salad']['lineId']}/ready")->assertOk();

    $this->getJson('/api/front-of-house/to-serve')->assertExactJson([]);
    $this->artisan('queue:work', ['--stop-when-empty' => true, '--max-jobs' => 20, '--memory' => 2048])->assertSuccessful();

    $pass = $this->getJson('/api/front-of-house/to-serve')->json();
    expect($pass)->toHaveCount(1)->and($pass[0]['dishName'])->toBe('Green salad');
    $this->postJson("/api/front-of-house/to-serve/{$pass[0]['id']}/serve")->assertOk();

    $this->postJson("/api/kitchen/tickets/{$first['ticketId']}/fire", ['course' => 'main'])->assertOk();
    $this->postJson("/api/kitchen/tickets/{$first['ticketId']}/fire", ['course' => 'drink'])->assertOk();
    $this->postJson("/api/kitchen/tickets/{$first['ticketId']}/lines/{$lines['Steak frites']['lineId']}/ready")->assertOk();
    $this->postJson("/api/kitchen/tickets/{$first['ticketId']}/lines/{$lines['House red']['lineId']}/ready")->assertOk();
    expect($this->getJson('/api/kitchen/screen')->json()[0]['done'])->toBeTrue();

    $this->artisan('queue:work', ['--stop-when-empty' => true, '--max-jobs' => 20, '--memory' => 2048])->assertSuccessful();
    foreach ($this->getJson('/api/front-of-house/to-serve')->json() as $dish) {
        $this->postJson("/api/front-of-house/to-serve/{$dish['id']}/serve")->assertOk();
    }

    $this->postJson('/api/front-of-house/tables/table-4/orders', [
        'lines' => [
            ['menuItemId' => 'creme-brulee', 'quantity' => 2, 'course' => 'dessert'],
        ],
    ])->assertJson(['accepted' => true]);

    $screen = $this->getJson('/api/kitchen/screen')->json();
    expect($screen)->toHaveCount(2);
    $dessert = $screen[1];
    $this->postJson("/api/kitchen/tickets/{$dessert['ticketId']}/fire", ['course' => 'dessert'])->assertOk();
    $this->postJson("/api/kitchen/tickets/{$dessert['ticketId']}/lines/{$dessert['lines'][0]['lineId']}/ready")->assertOk();
    $this->artisan('queue:work', ['--stop-when-empty' => true, '--max-jobs' => 20, '--memory' => 2048])->assertSuccessful();
    foreach ($this->getJson('/api/front-of-house/to-serve')->json() as $dish) {
        $this->postJson("/api/front-of-house/to-serve/{$dish['id']}/serve")->assertOk();
    }

    $this->postJson('/api/kitchen/dishes/dish-steak/eighty-six')
        ->assertExactJson(['dishId' => 'dish-steak', 'accepted' => true]);
    $steak = collect($this->getJson('/api/front-of-house/menu')->json())->firstWhere('menuItemId', 'steak-frites');
    expect($steak['available'])->toBeFalse();
    $this->postJson('/api/front-of-house/tables/table-4/orders', [
        'lines' => [
            ['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main'],
        ],
    ])->assertUnprocessable()->assertJson(['message' => 'Steak frites is unavailable.']);

    $this->postJson('/api/front-of-house/tables/table-4/bill')
        ->assertExactJson(['tableId' => 'table-4', 'accepted' => true]);

    $bill = $this->getJson('/api/billing/covers/table-4/bill')->assertOk()->json();
    expect($bill['lines'])->toHaveCount(4)
        ->and($bill['foodGrossCents'])->toBe(5500)
        ->and($bill['alcoholGrossCents'])->toBe(1200)
        ->and($bill['vatCents'])->toBe(700)
        ->and($bill['totalCents'])->toBe(6700)
        ->and($bill['balanceCents'])->toBe(6700)
        ->and($bill['status'])->toBe('open');

    $this->postJson("/api/billing/bills/{$bill['billId']}/payments", ['amountCents' => 4000])->assertOk();
    $stillSeated = collect($this->getJson('/api/front-of-house/floor-plan')->json())->firstWhere('tableId', 'table-4');
    expect($stillSeated['status'])->toBe('occupied')
        ->and($this->getJson('/api/billing/covers/table-4/bill')->json()['balanceCents'])->toBe(2700);

    $this->postJson("/api/billing/bills/{$bill['billId']}/payments", [
        'amountCents' => 2700,
        'tipCents' => 500,
    ])->assertOk();

    $paid = $this->getJson('/api/billing/covers/table-4/bill')->json();
    $freed = collect($this->getJson('/api/front-of-house/floor-plan')->json())->firstWhere('tableId', 'table-4');

    expect($paid['status'])->toBe('paid')
        ->and($paid['balanceCents'])->toBe(0)
        ->and($paid['tipCents'])->toBe(500)
        ->and($freed)->toMatchArray(['guestCount' => 0, 'status' => 'free']);

    $this->postJson("/api/billing/bills/{$bill['billId']}/payments", ['amountCents' => 1])
        ->assertUnprocessable()
        ->assertJson(['message' => 'A paid bill cannot be changed.']);

    expect(DB::table(Tables::TO_SERVE)->whereNull('served_at')->count())->toBe(0);
});
