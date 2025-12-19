<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RoomController;

Route::get('/', [IndexController::class, 'index']);

Route::get('/reviews', [PageController::class, 'reviewsPage'])->name('reviews');
Route::get('/privacy', [PageController::class, 'privacyPage'])->name('privacy');
Route::get('/terms', [PageController::class, 'termsPage'])->name('terms');

use App\Http\Controllers\AuthController;

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Redirect old admin login to unified login
Route::redirect('/admin/login', '/login');

// Password Reset Routes
Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');

// Invitation Registration
Route::get('/register/invite', \App\Livewire\RegisterInvitedStudent::class)->name('student.invitation');

Route::middleware(['auth'])->group(function () {
    Route::get('/rooms/{room}/start', [RoomController::class, 'start'])->name('rooms.start');
    Route::get('/rooms/{room}/join', [RoomController::class, 'join'])->name('rooms.join');
    Route::get('/rooms/{room}/stop', [RoomController::class, 'stop'])->name('rooms.stop');

    // Google Calendar Integration
    Route::get('/google/calendar/connect', [\App\Http\Controllers\GoogleCalendarController::class, 'redirectToGoogle'])->name('google.calendar.connect');
    Route::get('/google/calendar/callback', [\App\Http\Controllers\GoogleCalendarController::class, 'handleGoogleCallback'])->name('google.calendar.callback');
    Route::get('/google/calendar/disconnect', [\App\Http\Controllers\GoogleCalendarController::class, 'disconnect'])->name('google.calendar.disconnect');
    Route::get('/google/calendar/sync', [\App\Http\Controllers\GoogleCalendarController::class, 'syncSchedule'])->name('google.calendar.sync');
});

Route::get('/{username}', [PageController::class, 'tutorPage'])->name('tutors.show');