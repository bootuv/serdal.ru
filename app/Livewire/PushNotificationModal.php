<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class PushNotificationModal extends Component
{
    public bool $show = false;
    public bool $isSupported = true;
    public bool $permissionDenied = false;

    public function mount(): void
    {
        $this->checkIfShouldShow();
    }

    public function checkIfShouldShow(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        // Check removed to allow multi-device subscriptions
        // if ($user->pushSubscriptions()->exists()) {
        //     return;
        // }

        // Don't show if max reminders reached (3)
        if ($user->push_reminder_count >= 3) {
            return;
        }

        // Don't show if reminder is scheduled for future
        if ($user->push_reminder_at && $user->push_reminder_at->isFuture()) {
            return;
        }

        // Show the modal
        $this->show = true;
    }

    public function remindLater(): void
    {
        $user = Auth::user();
        if ($user) {
            $user->update([
                'push_reminder_at' => now()->addWeek(),
                'push_reminder_count' => $user->push_reminder_count + 1,
            ]);
        }
        $this->show = false;
    }

    public function dismiss(): void
    {
        // User subscribed via JS, just close modal
        $this->show = false;
    }

    public function getVapidPublicKey(): string
    {
        return config('webpush.vapid.public_key', '');
    }

    public function render()
    {
        return view('livewire.push-notification-modal');
    }
}
