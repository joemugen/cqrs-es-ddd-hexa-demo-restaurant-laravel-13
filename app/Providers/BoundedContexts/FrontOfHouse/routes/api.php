<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\BillRequestController;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\FloorPlanController;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\GuestController;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\MenuController;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\TableOrderController;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\ToServeController;
use Illuminate\Support\Facades\Route;

Route::post('/tables/{tableId}/guests', [GuestController::class, 'store'])
    ->name('front-of-house.guests.store');

Route::get('/floor-plan', FloorPlanController::class)
    ->name('front-of-house.floor-plan');

Route::get('/menu', MenuController::class)
    ->name('front-of-house.menu');

Route::post('/tables/{tableId}/orders', [TableOrderController::class, 'store'])
    ->name('front-of-house.orders.store');

Route::get('/to-serve', [ToServeController::class, 'index'])
    ->name('front-of-house.to-serve');

Route::post('/to-serve/{toServeId}/serve', [ToServeController::class, 'serve'])
    ->name('front-of-house.to-serve.serve');

Route::post('/tables/{tableId}/bill', [BillRequestController::class, 'store'])
    ->name('front-of-house.bills.store');
