<?php

use App\Http\Controllers\DemoController;
use Illuminate\Support\Facades\Route;

// Демо-кабинет учителя (страница «О платформе»). Подключён в bootstrap/app.php без группы web.
Route::get('/demo/teacher/{path?}', [DemoController::class, 'teacher'])->where('path', '.*')->name('demo.teacher');
