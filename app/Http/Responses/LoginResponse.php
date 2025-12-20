<?php

namespace App\Http\Responses;

use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;
use App\Models\User;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = auth()->user();

        // Проверяем, не заблокирован ли пользователь
        if ($user->is_blocked) {
            auth()->logout();

            return redirect()->route('filament.admin.auth.login')
                ->with('error', 'Ваш профиль деактивирован. Обратитесь к администратору.');
        }

        $defaultUrl = match ($user->role) {
            User::ROLE_ADMIN => '/admin',
            User::ROLE_STUDENT => '/student',
            default => '/tutor',
        };

        return redirect()->intended($defaultUrl);
    }
}
