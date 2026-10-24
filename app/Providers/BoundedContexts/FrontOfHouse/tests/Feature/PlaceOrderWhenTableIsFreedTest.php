<?php

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\FreeTable;
use App\Providers\BoundedContexts\FrontOfHouse\Application\MenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableOrderRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\RequestedLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use Illuminate\Support\Facades\DB;

describe('an order placed as the table is freed', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
        $this->visit = $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk()->json('visitId');
    });

    test('is refused once the table is freed: nobody orders for guests who left', function () {
        app(FrontOfHouseCommandBus::class)->dispatch(new FreeTable(new VisitId($this->visit)));

        $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [['menuItemId' => 'creme-brulee', 'quantity' => 1, 'course' => 'dessert']]])
            ->assertStatus(422)
            ->assertJson(['message' => 'No order for a table without seated guests.']);
    });

    test('is accepted when it was decided just before: the window is assumed, and the order is closed with the others', function () {
        $table = app(TableRepository::class)->get(new TableId('table-4'));
        $late = TableOrder::place(
            new TableOrderId('late-order'),
            $table,
            app(MenuRepository::class)->containing([new MenuItemId('creme-brulee')]),
            [new RequestedLine(new MenuItemId('creme-brulee'), 1, 'dessert')],
        );
        app(FrontOfHouseCommandBus::class)->dispatch(new FreeTable(new VisitId($this->visit)));

        app(TableOrderRepository::class)->save($late);

        expect(DB::table('foh_table_orders')->where('id', 'late-order')->exists())->toBeTrue();
    });
});
