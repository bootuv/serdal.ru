<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BigBlueButtonWebhookController;
use App\Models\Room;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;

Route::post('/bbb-webhook', BigBlueButtonWebhookController::class)->name('api.bbb.webhook');

// Public endpoint to check room status (for guest waiting page)
Route::get('/rooms/{room}/status', function (Room $room) {
    // Apply BBB settings from room owner
    $owner = $room->user;
    if ($owner && $owner->bbb_url && $owner->bbb_secret) {
        config([
            'bigbluebutton.BBB_SERVER_BASE_URL' => $owner->bbb_url,
            'bigbluebutton.BBB_SECURITY_SALT' => $owner->bbb_secret,
        ]);
    } else {
        // Check Global Admin Settings
        $globalUrl = \App\Models\Setting::where('key', 'bbb_url')->value('value');
        $globalSecret = \App\Models\Setting::where('key', 'bbb_secret')->value('value');

        if ($globalUrl && $globalSecret) {
            config([
                'bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl,
                'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret,
            ]);
        }
    }

    try {
        $isRunning = Bigbluebutton::isMeetingRunning(['meetingID' => $room->meeting_id]);
        return response()->json(['is_running' => $isRunning]);
    } catch (\Exception $e) {
        return response()->json(['is_running' => false, 'error' => 'Failed to check status']);
    }
})->name('api.rooms.status');
