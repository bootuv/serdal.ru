<?php

namespace App\Livewire\Cabinet\Admin\Concerns;

use App\Models\User;

/**
 * Доступ к экранам новой админки (/cabinet/admin): только администратор.
 * Использование: `use AdminScreen;` и `$this->authorizeAdmin()` в начале mount().
 */
trait AdminScreen
{
    protected function authorizeAdmin(): User
    {
        $user = auth()->user();
        abort_unless($user && $user->role === User::ROLE_ADMIN, 403);

        return $user;
    }
}
