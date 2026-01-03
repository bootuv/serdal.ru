<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use NotificationChannels\WebPush\PushSubscription;

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
        $endpoint = $request->input('endpoint');

        // Remove this endpoint from other users (handles browser shared between accounts)
        PushSubscription::where('endpoint', $endpoint)
            ->where('subscribable_id', '!=', $user->id)
            ->delete();

        $user->updatePushSubscription(
            $endpoint,
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

    /**
     * Check if user has any push subscriptions.
     */
    public function check(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'hasSubscription' => $user->pushSubscriptions()->exists(),
        ]);
    }

    /**
     * Remove all push subscriptions for the user.
     * Used when browser subscription is invalid but server still has record.
     */
    public function cleanup(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->pushSubscriptions()->delete();

        return response()->json([
            'success' => true,
            'message' => 'All push subscriptions cleaned up.',
        ]);
    }
}
