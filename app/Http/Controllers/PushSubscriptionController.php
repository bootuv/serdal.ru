<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PushSubscriptionController extends Controller
{
    /**
     * Store a new push subscription.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|url',
            'keys.auth' => 'required|string',
            'keys.p256dh' => 'required|string',
        ]);

        $user = $request->user();

        $user->updatePushSubscription(
            $request->input('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth'),
            $request->input('content_encoding', 'aesgcm')
        );

        return response()->json([
            'success' => true,
            'message' => 'Push subscription saved.',
        ]);
    }

    /**
     * Delete a push subscription.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $endpoint = $request->input('endpoint');

        if ($endpoint) {
            $user->deletePushSubscription($endpoint);
        } else {
            // Delete all subscriptions for this user
            $user->pushSubscriptions()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Push subscription removed.',
        ]);
    }
}
