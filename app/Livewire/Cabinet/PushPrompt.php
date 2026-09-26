<?php

namespace App\Livewire\Cabinet;

use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Окна про уведомления в браузере (макеты SyPush, SyPushBlocked).
 * «Включить уведомления?» — пока человек не включил их на этом устройстве; «Не сейчас» — напомним через неделю, не больше 3 раз.
 * «Уведомления заблокированы» — если браузер запретил их (после отказа или по событию push-blocked из профиля).
 */
class PushPrompt extends Component
{
    private const MAX_REMINDERS = 3;

    /** Можно ли сегодня предлагать включить (сам браузер проверяет, включены ли уже). */
    public bool $mayAsk = false;

    public bool $blocked = false;

    public function mount(): void
    {
        $user = auth()->user();
        $this->mayAsk = $user
            && $user->push_reminder_count < self::MAX_REMINDERS
            && ! ($user->push_reminder_at && $user->push_reminder_at->isFuture());
    }

    public function later(): void
    {
        $user = auth()->user();
        $user->update([
            'push_reminder_at' => now()->addWeek(),
            'push_reminder_count' => $user->push_reminder_count + 1,
        ]);
        $this->mayAsk = false;
        $this->dispatch('toast', message: 'Хорошо, напомним через неделю');
    }

    public function enabled(): void
    {
        $this->mayAsk = false;
        $this->blocked = false;
        $this->dispatch('toast', message: 'Уведомления включены');
    }

    #[On('push-blocked')]
    public function showBlocked(): void
    {
        $this->mayAsk = false;
        $this->blocked = true;
    }

    public function closeBlocked(): void
    {
        $this->blocked = false;
    }

    public function render()
    {
        return view('livewire.cabinet.push-prompt', [
            'vapid' => (string) config('webpush.vapid.public_key', ''),
        ]);
    }
}
