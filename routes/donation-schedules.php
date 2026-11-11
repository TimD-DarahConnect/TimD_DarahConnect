<?php

use App\Http\Controllers\DonationSchedule\DonationScheduleController;
use App\Http\Controllers\DonationSchedule\MobileUnitEventController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:petugas_udd,admin'])
    ->prefix('udd/schedules')
    ->name('schedules.')
    ->group(function () {
        Route::get('/', [DonationScheduleController::class, 'index'])->name('index');
        Route::get('/create', [DonationScheduleController::class, 'create'])->name('create');
        Route::post('/', [DonationScheduleController::class, 'store'])->name('store');

        Route::get('/mobile-units', [MobileUnitEventController::class, 'index'])->name('mobile.index');
        Route::get('/mobile-units/create', [MobileUnitEventController::class, 'create'])->name('mobile.create');
        Route::post('/mobile-units', [MobileUnitEventController::class, 'store'])->name('mobile.store');
        Route::get('/mobile-units/{schedule}/edit', [MobileUnitEventController::class, 'edit'])
            ->whereNumber('schedule')
            ->name('mobile.edit');
        Route::put('/mobile-units/{schedule}', [MobileUnitEventController::class, 'update'])
            ->whereNumber('schedule')
            ->name('mobile.update');
        Route::patch('/{schedule}/publication', [MobileUnitEventController::class, 'updatePublication'])
            ->whereNumber('schedule')
            ->name('publication.update');

        Route::get('/{schedule}', [DonationScheduleController::class, 'show'])
            ->whereNumber('schedule')
            ->name('show');
    });
