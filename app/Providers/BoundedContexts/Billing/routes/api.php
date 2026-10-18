<?php

use App\Providers\BoundedContexts\Billing\Infrastructure\Http\PayBillController;
use App\Providers\BoundedContexts\Billing\Infrastructure\Http\ShowBillController;
use Illuminate\Support\Facades\Route;

Route::get('/covers/{coverRef}/bill', ShowBillController::class)
    ->name('billing.bills.show');

Route::post('/bills/{billId}/payments', PayBillController::class)
    ->name('billing.payments.store');
