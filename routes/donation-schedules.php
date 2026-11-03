<?php

use App\Http\Controllers\DonationSchedule\DonationScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:petugas_udd,admin'])
    ->prefix('udd/schedules')
    ->name('schedules.')
    ->group(function () {
        Route::get('/', [DonationScheduleController::class, 'index'])->name('index');
        Route::get('/create', [DonationScheduleController::class, 'create'])->name('create');
        Route::post('/', [DonationScheduleController::class, 'store'])->name('store');
        Route::get('/{schedule}', [DonationScheduleController::class, 'show'])
            ->whereNumber('schedule')
            ->name('show');
    });
