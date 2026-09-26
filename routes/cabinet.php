<?php

/*
 * Новые кабинеты учителя и ученика (Livewire + Blade, docs/design/BRAND.md).
 * Работают параллельно со старыми Filament-панелями /student и /tutor; экраны переключаем по одному.
 */

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', \App\Http\Middleware\CheckUserActive::class])
    ->prefix('cabinet')
    ->name('cabinet.')
    ->group(function () {
        Route::get('/student', \App\Livewire\Cabinet\Student\Home::class)->name('student.home');
    });
