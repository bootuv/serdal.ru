<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;
use App\Models\User;

/** Куда вести после входа и сброса пароля: заблокированного — обратно на вход, остальных — в свой кабинет. */
class LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = auth()->user();

        // Проверяем, не заблокирован ли пользователь
        if ($user->is_blocked) {
            auth()->logout();

            return redirect()->route('login')->with('blocked_email', $user->email);
        }

        return redirect()->intended(\App\Http\Middleware\EnsureCabinetRole::homeFor($user));
    }
}
