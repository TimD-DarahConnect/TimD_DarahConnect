<?php

use App\Http\Controllers\DonorDiscovery\DonorDiscoveryController;
use Illuminate\Support\Facades\Route;

Route::prefix('donor/discovery')
    ->name('discovery.')
    ->group(function () {
        Route::get('/', [DonorDiscoveryController::class, 'index'])->name('index');
        Route::get('/{bloodRequest}', [DonorDiscoveryController::class, 'show'])
            ->whereNumber('bloodRequest')
            ->name('show');
    });
