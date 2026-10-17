<?php

use App\Providers\BoundedContexts\Billing\Infrastructure\Http\BillController;
use App\Providers\BoundedContexts\Billing\Infrastructure\Http\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/covers/{coverRef}/bill', [BillController::class, 'show'])
    ->name('billing.bills.show');

Route::post('/bills/{billId}/payments', [PaymentController::class, 'store'])
    ->name('billing.payments.store');
