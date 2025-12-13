<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RoomController;

Route::get('/', [IndexController::class, 'index']);

Route::get('/reviews', [PageController::class, 'reviewsPage'])->name('reviews');

Route::redirect('/login', '/app/login')->name('login');

Route::middleware(['auth'])->group(function () {
    Route::get('/rooms/{room}/start', [RoomController::class, 'start'])->name('rooms.start');
    Route::get('/rooms/{room}/join', [RoomController::class, 'join'])->name('rooms.join');
    Route::get('/rooms/{room}/stop', [RoomController::class, 'stop'])->name('rooms.stop');
});

Route::get('/{username}', [PageController::class, 'tutorPage'])->name('tutors.show');