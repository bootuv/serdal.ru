<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/** Запоминает время входа: «Последний вход» в карточке пользователя админки. */
class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            // Без событий модели: вход не должен запускать логику сохранения профиля
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        }
    }
}
