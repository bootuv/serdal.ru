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

// Unified login - redirect to admin panel login
Route::get('/login', fn() => redirect('/admin/login'))->name('login');
Route::get('/application', \App\Livewire\BecomeTutorPage::class)->name('become-tutor');

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

    // Push Notifications
    Route::post('/push-subscription', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('/push-subscription', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
});

Route::get('/{username}', [PageController::class, 'tutorPage'])->name('tutors.show');