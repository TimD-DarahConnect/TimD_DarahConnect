<?php

use App\Http\Controllers\EmergencyRequest\EmergencyRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:pencari_darah'])
    ->prefix('emergency-requests')
    ->name('emergency-requests.')
    ->group(function () {
        Route::get('/', [EmergencyRequestController::class, 'index'])->name('index');
        Route::get('/create', [EmergencyRequestController::class, 'create'])->name('create');
        Route::post('/', [EmergencyRequestController::class, 'store'])->name('store');
        Route::get('/{bloodRequest}', [EmergencyRequestController::class, 'show'])
            ->whereNumber('bloodRequest')
            ->name('show');
        Route::get('/{bloodRequest}/edit', [EmergencyRequestController::class, 'edit'])
            ->whereNumber('bloodRequest')
            ->name('edit');
        Route::put('/{bloodRequest}', [EmergencyRequestController::class, 'update'])
            ->whereNumber('bloodRequest')
            ->name('update');
    });

Route::middleware(['auth', 'role:pencari_darah,petugas_udd,petugas_rs,admin'])
    ->get('/emergency-requests/{bloodRequest}/referral-letter', [EmergencyRequestController::class, 'referralLetter'])
    ->whereNumber('bloodRequest')
    ->name('emergency-requests.referral-letter');
