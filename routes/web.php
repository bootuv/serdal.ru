<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Http\Controllers\PageController;

Route::get('/', function () {
    return view('index');
});

Route::get('/reviews', [PageController::class, 'reviewsPage'])->name('reviews');

Route::get('/{username}', function ($id) {
    $user = User::whereUsername($id)->with(['directs', 'subjects'])->firstOrFail();

    return view('tutor', compact('user'));
})->name('tutors.show');