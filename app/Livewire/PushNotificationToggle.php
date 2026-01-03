<?php

namespace App\Livewire;

use Livewire\Component;

class PushNotificationToggle extends Component
{
    public bool $isSubscribed = false;

    public function mount(): void
    {
        $user = auth()->user();
        if ($user) {
            $this->isSubscribed = $user->pushSubscriptions()->exists();
        }
    }

    public function getVapidPublicKey(): string
    {
        return config('webpush.vapid.public_key', '');
    }

    public function render()
    {
        return view('livewire.push-notification-toggle');
    }
}
