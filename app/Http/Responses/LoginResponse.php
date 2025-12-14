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

        $defaultUrl = match ($user->role) {
            User::ROLE_ADMIN => '/admin',
            User::ROLE_STUDENT => '/student',
            default => '/tutor',
        };

        return redirect()->intended($defaultUrl);
    }
}
