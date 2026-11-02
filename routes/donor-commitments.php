<?php

use App\Http\Controllers\DonorCommitment\DonorCommitmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:pendonor,pencari_darah'])
    ->get('/commitments', [DonorCommitmentController::class, 'index'])
    ->name('commitments.index');

Route::middleware(['auth', 'role:pendonor'])
    ->prefix('commitments/requests/{bloodRequest}')
    ->whereNumber('bloodRequest')
    ->group(function () {
        Route::get('/create', [DonorCommitmentController::class, 'create'])->name('commitments.create');
        Route::post('/', [DonorCommitmentController::class, 'store'])->name('commitments.store');
    });

Route::middleware(['auth', 'role:pendonor,pencari_darah,petugas_udd,petugas_rs,admin'])
    ->get('/commitments/{commitment}', [DonorCommitmentController::class, 'show'])
    ->whereNumber('commitment')
    ->name('commitments.show');
