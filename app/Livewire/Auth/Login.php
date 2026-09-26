<?php

namespace App\Livewire\Auth;

use App\Http\Middleware\EnsureCabinetRole;
use App\Http\Responses\LoginResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Вход в кабинет для учителей и учеников (админ тоже входит здесь). Макеты: AuthLogin, AuthLoginMobile, AuthBlocked. */
#[Layout('components.layouts.auth', ['title' => 'Вход'])]
class Login extends Component
{
    private const MAX_ATTEMPTS = 10;

    public string $email = '';

    public string $password = '';

    /** Почта заблокированного аккаунта — показываем экран «Доступ приостановлен». */
    #[Locked]
    public ?string $blocked = null;

    public function mount()
    {
        if (Auth::check()) {
            return redirect(EnsureCabinetRole::homeFor(Auth::user()));
        }

        // Заблокировали, пока человек был в кабинете (CheckUserActive)
        $this->blocked = session('blocked_email');
    }

    public function login()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Введите почту.',
            'email.email' => 'Проверьте почту — в ней ошибка.',
            'password.required' => 'Введите пароль.',
        ]);

        $key = 'login:' . Str::lower($this->email) . '|' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            throw ValidationException::withMessages([
                'email' => 'Слишком много попыток. Попробуйте через ' . plural_ru($minutes, 'минуту', 'минуты', 'минут') . '.',
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], true)) {
            RateLimiter::hit($key);
            $this->password = '';
            throw ValidationException::withMessages(['email' => 'Неверная почта или пароль.']);
        }

        RateLimiter::clear($key);

        if (Auth::user()->is_blocked) {
            $this->blocked = Auth::user()->email;
            Auth::logout();
            $this->password = '';

            return null;
        }

        session()->regenerate();

        return (new LoginResponse)->toResponse(request());
    }

    public function back(): void
    {
        $this->blocked = null;
        $this->email = '';
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
