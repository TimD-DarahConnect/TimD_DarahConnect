<?php

use App\Http\Controllers\MedicalVerification\MedicalVerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:petugas_udd,admin'])
    ->prefix('udd/verifications')
    ->name('verifications.')
    ->group(function () {
        Route::get('/', [MedicalVerificationController::class, 'index'])->name('index');
        Route::get('/{bloodRequest}', [MedicalVerificationController::class, 'show'])
            ->whereNumber('bloodRequest')
            ->name('show');
        Route::get('/{bloodRequest}/document', [MedicalVerificationController::class, 'document'])
            ->whereNumber('bloodRequest')
            ->name('document');
        Route::post('/{bloodRequest}/approve', [MedicalVerificationController::class, 'approve'])
            ->whereNumber('bloodRequest')
            ->name('approve');
        Route::post('/{bloodRequest}/reject', [MedicalVerificationController::class, 'reject'])
            ->whereNumber('bloodRequest')
            ->name('reject');
    });
