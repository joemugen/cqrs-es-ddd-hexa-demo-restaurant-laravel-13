<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\ListFloorPlanController;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\ListMenuController;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\ListToServeController;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\PlaceTableOrderController;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\RequestBillController;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\SeatGuestsController;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\ServeDishController;
use Illuminate\Support\Facades\Route;

Route::post('/tables/{tableId}/guests', SeatGuestsController::class)
    ->name('front-of-house.guests.store');

Route::get('/floor-plan', ListFloorPlanController::class)
    ->name('front-of-house.floor-plan');

Route::get('/menu', ListMenuController::class)
    ->name('front-of-house.menu');

Route::post('/tables/{tableId}/orders', PlaceTableOrderController::class)
    ->name('front-of-house.orders.store');

Route::get('/to-serve', ListToServeController::class)
    ->name('front-of-house.to-serve');

Route::post('/to-serve/{toServeId}/serve', ServeDishController::class)
    ->name('front-of-house.to-serve.serve');

Route::post('/tables/{tableId}/bill', RequestBillController::class)
    ->name('front-of-house.bills.store');
