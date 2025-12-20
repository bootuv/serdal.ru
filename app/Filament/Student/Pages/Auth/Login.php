<?php

namespace App\Filament\Student\Pages\Auth;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function authenticate(): ?\Filament\Http\Responses\Auth\Contracts\LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        }

        $data = $this->form->getState();

        // Проверяем учетные данные
        if (
            !auth()->attempt([
                'email' => $data['email'],
                'password' => $data['password'],
            ], $data['remember'] ?? false)
        ) {
            throw ValidationException::withMessages([
                'data.email' => __('filament-panels::pages/auth/login.messages.failed'),
            ]);
        }

        $user = auth()->user();

        // Проверяем, не заблокирован ли пользователь
        if ($user->is_blocked) {
            auth()->logout();

            throw ValidationException::withMessages([
                'data.email' => 'Ваш профиль деактивирован. Обратитесь к администратору.',
            ]);
        }

        session()->regenerate();

        return app(\Filament\Http\Responses\Auth\Contracts\LoginResponse::class);
    }
}
