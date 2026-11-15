<?php

use App\Http\Controllers\SessionBooking\AnamnesisScreeningController;
use App\Http\Controllers\SessionBooking\BookingTicketController;
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

            Route::get('/{booking}/screening', [AnamnesisScreeningController::class, 'show'])
                ->whereNumber('booking')
                ->name('screening.show');
            Route::post('/{booking}/screening', [AnamnesisScreeningController::class, 'store'])
                ->whereNumber('booking')
                ->name('screening.store');

            Route::patch('/{booking}/reschedule', [BookingTicketController::class, 'reschedule'])
                ->whereNumber('booking')
                ->name('reschedule');
            Route::patch('/{booking}/cancel', [BookingTicketController::class, 'cancel'])
                ->whereNumber('booking')
                ->name('cancel');
        });

        Route::middleware(['auth', 'role:pendonor,petugas_udd,admin'])->group(function () {
            Route::get('/{booking}/ticket', [BookingTicketController::class, 'ticket'])
                ->whereNumber('booking')
                ->name('ticket');
            Route::get('/{booking}', [SessionBookingController::class, 'show'])
                ->whereNumber('booking')
                ->name('show');
        });
    });
