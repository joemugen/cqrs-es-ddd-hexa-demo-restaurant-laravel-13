<?php

use App\Providers\BoundedContexts\Kitchen\Infrastructure\Http\FireDishesController;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Http\KitchenScreenController;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Http\MarkDishReadyController;
use Illuminate\Support\Facades\Route;

Route::get('/screen', KitchenScreenController::class)
    ->name('kitchen.screen');

Route::post('/tickets/{ticketId}/fire', [FireDishesController::class, 'store'])
    ->name('kitchen.tickets.fire');

Route::post('/tickets/{ticketId}/lines/{lineId}/ready', [MarkDishReadyController::class, 'store'])
    ->name('kitchen.tickets.ready');
