<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\PageController;

Route::get('/', [IndexController::class, 'index']);

Route::get('/reviews', [PageController::class, 'reviewsPage'])->name('reviews');
Route::get('/{username}', [PageController::class, 'tutorPage'])->name('tutors.show');