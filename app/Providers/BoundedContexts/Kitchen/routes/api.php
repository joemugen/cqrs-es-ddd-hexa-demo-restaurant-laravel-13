<?php

use App\Providers\BoundedContexts\Kitchen\Infrastructure\Http\DeclareDishEightySixedController;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Http\FireDishesController;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Http\ListKitchenScreenController;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Http\MarkDishReadyController;
use Illuminate\Support\Facades\Route;

Route::get('/screen', ListKitchenScreenController::class)
    ->name('kitchen.screen');

Route::post('/tickets/{ticketId}/fire', FireDishesController::class)
    ->name('kitchen.tickets.fire');

Route::post('/tickets/{ticketId}/lines/{lineId}/ready', MarkDishReadyController::class)
    ->name('kitchen.tickets.ready');

Route::post('/dishes/{dishId}/eighty-six', DeclareDishEightySixedController::class)
    ->name('kitchen.dishes.eighty-six');
