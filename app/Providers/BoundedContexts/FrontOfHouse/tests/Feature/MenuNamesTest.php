<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

describe('the menu', function () {
    beforeEach(fn () => $this->seed(FrontOfHouseSeeder::class));

    test('knows a dish by its name, so two items cannot carry the same one', function () {
        $duplicate = fn () => DB::table(Tables::MENU_ITEMS)->insert([
            'id' => 'another-steak',
            'name' => 'Steak frites',
            'description' => 'Same name, another id.',
            'price_cents' => 2500,
            'allergens' => '[]',
            'available' => true,
            'kind' => 'food',
        ]);

        expect($duplicate)->toThrow(QueryException::class);
    });
});
