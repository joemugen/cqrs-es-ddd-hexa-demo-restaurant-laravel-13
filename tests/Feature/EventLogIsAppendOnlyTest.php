<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use Illuminate\Support\Facades\DB;

/**
 * @param  list<string>  $statements
 * @return list<string> the kinds of statement that wrote to the events table, such as "insert into"
 */
function writesToTheEventLog(array $statements): array
{
    $kinds = [];

    foreach ($statements as $sql) {
        if (preg_match('/^\s*(insert into|insert or replace into|replace into|update|delete from)\s+["`]?events["`]?\b/i', $sql, $match) === 1) {
            $kinds[] = strtolower($match[1]);
        }
    }

    return array_values(array_unique($kinds));
}

/**
 * @return list<array<string, mixed>> every stored event, oldest first
 */
function loggedEvents(): array
{
    return DB::table('events')->orderBy('position')->get()
        ->map(fn (object $row): array => (array) $row)
        ->all();
}

describe('the event log is append only', function (): void {
    beforeEach(function (): void {
        $this->statements = [];
        DB::listen(function ($query): void {
            $this->statements[] = $query->sql;
        });
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
    });

    it('receives nothing but INSERT statements during a whole service in sync mode', function (): void {
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [
            ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
            ['menuItemId' => 'house-red', 'quantity' => 2, 'course' => 'drink'],
        ]])->assertOk();
        $ticket = $this->getJson('/api/kitchen/screen')->json()[0];
        foreach (['starter', 'drink'] as $course) {
            $this->postJson("/api/kitchen/tickets/{$ticket['ticketId']}/fire", ['course' => $course])->assertOk();
        }
        foreach ($ticket['lines'] as $line) {
            $this->postJson("/api/kitchen/tickets/{$ticket['ticketId']}/lines/{$line['lineId']}/ready")->assertOk();
        }
        $this->artisan('queue:work', ['--stop-when-empty' => true, '--memory' => 2048])->assertSuccessful();
        foreach ($this->getJson('/api/front-of-house/to-serve')->json() as $dish) {
            $this->postJson("/api/front-of-house/to-serve/{$dish['id']}/serve")->assertOk();
        }
        $this->postJson('/api/kitchen/dishes/dish-steak/eighty-six')->assertOk();
        $this->postJson('/api/front-of-house/tables/table-4/bill')->assertOk();
        $bill = $this->getJson('/api/billing/covers/table-4/bill')->json();
        $this->postJson("/api/billing/bills/{$bill['billId']}/payments", ['amountCents' => $bill['totalCents']])->assertOk();

        expect(count(loggedEvents()))->toBeGreaterThan(8)
            ->and(writesToTheEventLog($this->statements))->toBe(['insert into']);
    });

    it('receives nothing but INSERT statements while workers consume it', function (): void {
        config(['consumption.mode' => 'worker']);
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [
            ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
        ]])->assertOk();

        foreach (['kitchen', 'front-of-house', 'billing'] as $context) {
            $this->artisan("{$context}:consume", ['--stop-when-empty' => true])->assertSuccessful();
        }

        expect(count(loggedEvents()))->toBeGreaterThan(1)
            ->and(writesToTheEventLog($this->statements))->toBe(['insert into']);
    });

    it('never changes a row once written: after each step the earlier events are exactly as they were', function (): void {
        $snapshots = [];

        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [
            ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
        ]])->assertOk();
        $snapshots[] = loggedEvents();

        $ticket = $this->getJson('/api/kitchen/screen')->json()[0];
        $this->postJson("/api/kitchen/tickets/{$ticket['ticketId']}/fire", ['course' => 'starter'])->assertOk();
        $snapshots[] = loggedEvents();

        $this->postJson("/api/kitchen/tickets/{$ticket['ticketId']}/lines/{$ticket['lines'][0]['lineId']}/ready")->assertOk();
        $snapshots[] = loggedEvents();

        $this->postJson('/api/kitchen/dishes/dish-steak/eighty-six')->assertOk();
        $snapshots[] = loggedEvents();

        foreach (array_keys($snapshots) as $i) {
            if ($i > 0) {
                expect(array_slice($snapshots[$i], 0, count($snapshots[$i - 1])))->toBe($snapshots[$i - 1]);
            }
        }

        expect(count($snapshots[3]))->toBeGreaterThan(count($snapshots[0]));
    });
});
