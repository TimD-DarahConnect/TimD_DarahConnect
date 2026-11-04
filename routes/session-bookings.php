<?php

use App\Http\Controllers\SessionBooking\SessionBookingController;
use Illuminate\Support\Facades\Route;

Route::prefix('donor/bookings')
    ->name('bookings.')
    ->group(function () {
        Route::get('/schedules', [SessionBookingController::class, 'schedules'])->name('schedules');
        Route::get('/schedules/{schedule}', [SessionBookingController::class, 'showSchedule'])
            ->whereNumber('schedule')
            ->name('schedules.show');

        Route::middleware(['auth', 'role:pendonor'])->group(function () {
            Route::get('/', [SessionBookingController::class, 'index'])->name('index');
            Route::post('/sessions/{session}', [SessionBookingController::class, 'store'])
                ->whereNumber('session')
                ->name('store');
        });

        Route::middleware(['auth', 'role:pendonor,petugas_udd,admin'])
            ->get('/{booking}', [SessionBookingController::class, 'show'])
            ->whereNumber('booking')
            ->name('show');
    });
