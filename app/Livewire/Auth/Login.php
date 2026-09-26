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
        // ?next= — куда вернуть после входа (например, на приглашение учителя); только адреса этого сайта
        $next = self::safeReturnUrl(request()->query('next'));

        if (Auth::check()) {
            return redirect($next ?? EnsureCabinetRole::homeFor(Auth::user()));
        }

        if ($next) {
            session()->put('url.intended', $next);
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

    /** Адрес возврата после входа: путь или полный адрес этого же сайта; чужие адреса и «//host» — null. */
    public static function safeReturnUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || strlen($value) > 2000 || preg_match('/[\\\\\x00-\x1F\x7F]/', $value)) {
            return null;
        }

        if (str_starts_with($value, '/')) {
            return str_starts_with($value, '//') ? null : url($value);
        }

        $parts = parse_url($value);
        $host = parse_url(url('/'), PHP_URL_HOST);
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])
            || strcasecmp($parts['host'] ?? '', (string) $host) !== 0) {
            return null;
        }

        return $value;
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
