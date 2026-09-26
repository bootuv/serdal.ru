<?php

namespace App\Livewire;

use Livewire\Component;

class PushNotificationToggle extends Component
{
    public bool $isSubscribed = false;

    /** Вид: icon — кнопка-иконка (Filament), cabinet — переключатель для профиля в новых кабинетах. */
    public string $variant = 'icon';

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
        return view($this->variant === 'cabinet' ? 'livewire.cabinet.push-switch' : 'livewire.push-notification-toggle');
    }
}
