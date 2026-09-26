<?php

namespace App\Livewire;

use Livewire\Component;

/** Переключатель push-уведомлений в профиле учителя и ученика. Подписку оформляет window.PushNotifications. */
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
        return view('livewire.cabinet.push-switch');
    }
}
