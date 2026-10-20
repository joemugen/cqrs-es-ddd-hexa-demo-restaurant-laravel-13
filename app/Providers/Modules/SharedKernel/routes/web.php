<?php

use App\Providers\Modules\SharedKernel\Infrastructure\Http\ShowMetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/metrics', ShowMetricsController::class)->name('metrics');
