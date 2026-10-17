<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\FloorPlanController;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\GuestController;
use Illuminate\Support\Facades\Route;

Route::post('/tables/{tableId}/guests', [GuestController::class, 'store'])
    ->name('front-of-house.guests.store');

Route::get('/floor-plan', FloorPlanController::class)
    ->name('front-of-house.floor-plan');
