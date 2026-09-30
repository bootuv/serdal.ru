<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BigBlueButtonWebhookController;
use App\Models\Room;

Route::post('/bbb-webhook', BigBlueButtonWebhookController::class)->name('api.bbb.webhook');

// Public endpoint to check room status (for guest waiting page)
Route::get('/rooms/{room}/status', function (Room $room) {
    // Сервер, на котором идёт (или шло последним) занятие комнаты
    $server = app(\App\Services\Bbb\BbbServerPool::class)->forRoom($room);

    try {
        $isRunning = (bool) $server?->client()->isMeetingRunning(['meetingID' => $room->meeting_id]);
        return response()->json(['is_running' => $isRunning]);
    } catch (\Exception $e) {
        return response()->json(['is_running' => false, 'error' => 'Failed to check status']);
    }
})->name('api.rooms.status');
