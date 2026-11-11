<?php

use App\Http\Controllers\DonorCommitment\DonorCommitmentController;
use App\Http\Controllers\DonorCommitment\MeetingPointCoordinationController;
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

Route::middleware(['auth', 'role:pendonor'])
    ->prefix('commitments/{commitment}')
    ->whereNumber('commitment')
    ->group(function () {
        Route::patch('/arrive', [DonorCommitmentController::class, 'confirmArrival'])->name('commitments.arrive');
        Route::patch('/cancel', [DonorCommitmentController::class, 'cancel'])->name('commitments.cancel');
    });

Route::middleware(['auth', 'role:pendonor,pencari_darah'])
    ->prefix('commitments/{commitment}/coordination')
    ->whereNumber('commitment')
    ->name('coordinations.')
    ->group(function () {
        Route::get('/', [MeetingPointCoordinationController::class, 'show'])->name('show');
        Route::patch('/meeting-point', [MeetingPointCoordinationController::class, 'updateMeetingPoint'])->name('meeting-point.update');
        Route::patch('/confirm-point', [MeetingPointCoordinationController::class, 'confirmMeetingPoint'])->name('meeting-point.confirm');
        Route::post('/messages', [MeetingPointCoordinationController::class, 'storeMessage'])->name('messages.store');
    });

Route::middleware(['auth', 'role:pendonor,pencari_darah,petugas_udd,petugas_rs,admin'])
    ->get('/commitments/{commitment}', [DonorCommitmentController::class, 'show'])
    ->whereNumber('commitment')
    ->name('commitments.show');
