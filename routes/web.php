<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RoomController;

Route::get('/', [IndexController::class, 'index']);



Route::get('/reviews', [PageController::class, 'reviewsPage'])->name('reviews');
Route::get('/reviews/load-more', [PageController::class, 'loadMoreReviews'])->name('reviews.load-more');
Route::get('/privacy', [PageController::class, 'privacyPage'])->name('privacy');
Route::get('/terms', [PageController::class, 'termsPage'])->name('terms');

// Unified login - redirect to admin panel login
Route::get('/login', fn() => redirect('/admin/login'))->name('login');

// Teacher choice page - choose between new LMS and old Greenlight
Route::get('/welcome', fn() => view('welcome-choice'))->name('teacher.choice');
Route::get('/application', \App\Livewire\BecomeTutorPage::class)->name('become-tutor');

// Invitation Registration
Route::get('/register/invite', \App\Livewire\RegisterInvitedStudent::class)->name('student.invitation');

Route::middleware(['auth'])->group(function () {
    Route::get('/rooms/{room}/start', [RoomController::class, 'start'])->name('rooms.start');
    // Route::get('/rooms/{room}/join', [RoomController::class, 'join'])->name('rooms.join'); // Moved to public
    Route::get('/rooms/{room}/stop', [RoomController::class, 'stop'])->name('rooms.stop');

    // Session logout redirect - handles different roles after BBB session ends
    Route::get('/session/{session}/logout', \App\Http\Controllers\SessionLogoutController::class)->name('session.logout');

    // Google Calendar Integration
    Route::get('/google/calendar/connect', [\App\Http\Controllers\GoogleCalendarController::class, 'redirectToGoogle'])->name('google.calendar.connect');
    Route::get('/google/calendar/callback', [\App\Http\Controllers\GoogleCalendarController::class, 'handleGoogleCallback'])->name('google.calendar.callback');
    Route::get('/google/calendar/disconnect', [\App\Http\Controllers\GoogleCalendarController::class, 'disconnect'])->name('google.calendar.disconnect');
    Route::get('/google/calendar/sync', [\App\Http\Controllers\GoogleCalendarController::class, 'syncSchedule'])->name('google.calendar.sync');

    // Push Notifications
    Route::post('/push-subscription', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('/push-subscription', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    Route::get('/push-subscription/check', [\App\Http\Controllers\PushSubscriptionController::class, 'check'])->name('push.check');
    Route::delete('/push-subscription/cleanup', [\App\Http\Controllers\PushSubscriptionController::class, 'cleanup'])->name('push.cleanup');
});

Route::get('/{username}', [PageController::class, 'tutorPage'])->name('tutors.show');

// Public Room Access
Route::get('/rooms/{room}/join', \App\Livewire\GuestJoinRoom::class)->name('rooms.join');
Route::get('/rooms/{room}/connect', [RoomController::class, 'connect'])->name('rooms.connect');
Route::post('/rooms/{room}/join/guest', [RoomController::class, 'joinAsGuest'])->name('rooms.join.guest');