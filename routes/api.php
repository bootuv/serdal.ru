<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BigBlueButtonWebhookController;

Route::post('/bbb-webhook', BigBlueButtonWebhookController::class)->name('api.bbb.webhook');
