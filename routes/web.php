<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RoomController;

Route::get('/', [IndexController::class, 'index']);

// Temporary Debug Route (remove after use)
Route::get('/debug-image', function () {
    $info = [];
    $info['gd'] = extension_loaded('gd');
    $info['imagick'] = extension_loaded('imagick');

    try {
        $files = \Illuminate\Support\Facades\Storage::disk('s3')->allFiles('homework-submissions');
        $info['s3_access'] = 'OK, found ' . count($files) . ' files';
    } catch (\Exception $e) {
        $info['s3_access'] = 'Error: ' . $e->getMessage();
    }

    try {
        $tempPath = storage_path('app/livewire-tmp/test.txt');
        file_put_contents($tempPath, 'test');
        $info['temp_write'] = 'OK: ' . $tempPath;
    } catch (\Exception $e) {
        $info['temp_write'] = 'Error: ' . $e->getMessage();
    }

    return $info;
});

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
});

Route::get('/{username}', [PageController::class, 'tutorPage'])->name('tutors.show');