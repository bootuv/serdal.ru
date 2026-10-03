<?php

namespace App\Livewire\Cabinet;

use App\Models\User;
use App\Notifications\EmailChanged;
use App\Notifications\EmailVerificationCode;
use App\Support\MailDelivery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Почта и пароль (/cabinet/account). У учителя — вкладка «Почта и пароль» в профиле (embedded), адрес /cabinet/account ведёт туда.
 * Обе смены — через код из письма: почта — код на новый адрес и текущий пароль, пароль — код на текущую почту.
 * Незавершённая смена ждёт в сессии (account_change): что меняем, новое значение, хеш кода, срок, попытки.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Почта и пароль', 'active' => 'account'])]
class Account extends Component
{
    public const CODE_TTL_MINUTES = 30;

    private const MAX_CODE_ATTEMPTS = 5;

    private const SESSION_KEY = 'account_change';

    /** Открытая форма: null, email, password. */
    public ?string $editing = null;

    /** Шаг формы: form — ввод данных, code — ввод кода из письма. */
    public string $step = 'form';

    public string $newEmail = '';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $verification_code = '';

    public bool $codeResent = false;

    /** Встроен вкладкой в профиль учителя — без своей шапки. */
    public bool $embedded = false;

    public function mount(): void
    {
        if (! $this->embedded && auth()->user()->role === User::ROLE_TUTOR && \Illuminate\Support\Facades\Route::has('cabinet.teacher.profile')) {
            $this->redirectRoute('cabinet.teacher.profile', ['tab' => 'account']);

            return;
        }

        // Вернулись на страницу, пока код ещё действует, — сразу на ввод кода
        $pending = $this->pending();
        if ($pending) {
            $this->editing = $pending['kind'];
            $this->step = 'code';
        }
    }

    public function open(string $kind): void
    {
        abort_unless(in_array($kind, ['email', 'password'], true), 404);

        session()->forget(self::SESSION_KEY);
        $this->reset('newEmail', 'currentPassword', 'newPassword', 'verification_code', 'codeResent');
        $this->resetValidation();
        $this->editing = $kind;
        $this->step = 'form';
    }

    public function cancel(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->reset('editing', 'step', 'newEmail', 'currentPassword', 'newPassword', 'verification_code', 'codeResent');
        $this->resetValidation();
    }

    /** Шаг 1: проверить данные и отправить код. */
    public function sendCode(): void
    {
        $user = auth()->user();

        if ($this->editing === 'email') {
            $this->newEmail = Str::lower(trim($this->newEmail));
            $this->validate([
                'newEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
                'currentPassword' => ['required', 'string'],
            ], [
                'newEmail.required' => 'Введите новую почту',
                'newEmail.email' => 'Проверьте адрес — в нём ошибка',
                'newEmail.unique' => 'Эта почта уже занята другим аккаунтом',
                'currentPassword.required' => 'Введите текущий пароль',
            ]);
            if (Str::lower((string) $user->email) === $this->newEmail) {
                $this->addError('newEmail', 'Это ваша текущая почта');

                return;
            }
            if (! Hash::check($this->currentPassword, (string) $user->password)) {
                $this->addError('currentPassword', 'Пароль не подходит');

                return;
            }
            $value = $this->newEmail;
            $to = $this->newEmail;
        } elseif ($this->editing === 'password') {
            $this->validate([
                'newPassword' => ['required', 'string', 'min:8', 'max:255'],
            ], [
                'newPassword.required' => 'Придумайте новый пароль',
                'newPassword.min' => 'Пароль — минимум 8 символов',
                'newPassword.max' => 'Слишком длинный пароль',
            ]);
            // В сессии — только хеш пароля
            $value = Hash::make($this->newPassword);
            $to = (string) $user->email;
        } else {
            return;
        }

        if (! $this->throttleSend($this->editing === 'email' ? 'newEmail' : 'newPassword')) {
            return;
        }

        if (! $this->deliver($this->editing, $value, $to)) {
            $this->addError($this->editing === 'email' ? 'newEmail' : 'newPassword', MailDelivery::FAILED);

            return;
        }
        $this->reset('currentPassword', 'newPassword', 'verification_code', 'codeResent');
        $this->step = 'code';
    }

    public function resendCode(): void
    {
        $pending = $this->pending();
        if (! $pending) {
            $this->expired();

            return;
        }

        // Повторно — не чаще раза в минуту
        if (now()->lt(Carbon::parse($pending['sent_at'])->addMinute())) {
            $this->addError('verification_code', 'Новый код можно запросить через минуту после предыдущего.');

            return;
        }
        if (! $this->throttleSend('verification_code')) {
            return;
        }

        if (! $this->deliver($pending['kind'], $pending['value'], $pending['to'])) {
            $this->addError('verification_code', MailDelivery::FAILED);

            return;
        }
        $this->resetErrorBag('verification_code');
        $this->codeResent = true;
    }

    /** Шаг 2: проверить код и применить смену. */
    public function confirm(): void
    {
        $this->resetErrorBag();
        $pending = $this->pending();
        if (! $pending) {
            $this->expired();

            return;
        }

        $code = preg_replace('/\D/', '', $this->verification_code);
        if (strlen($code) !== 6) {
            $this->addError('verification_code', 'Введите все 6 цифр из письма');

            return;
        }

        if (! Hash::check($code, $pending['code_hash'])) {
            // После 5 неверных попыток код сгорает — нужен новый
            $pending['attempts']++;
            if ($pending['attempts'] >= self::MAX_CODE_ATTEMPTS) {
                session()->forget(self::SESSION_KEY);
                $this->step = 'form';
                $this->addError('code_expired', 'Слишком много неверных попыток — запросите новый код.');

                return;
            }
            session()->put(self::SESSION_KEY, $pending);
            $this->addError('verification_code', 'Код не подходит — проверьте цифры в письме');

            return;
        }

        /** @var User $user */
        $user = auth()->user();
        session()->forget(self::SESSION_KEY);

        if ($pending['kind'] === 'email') {
            // Пока ждали код, адрес мог занять кто-то другой
            if (User::where('email', $pending['value'])->whereKeyNot($user->id)->exists()) {
                $this->step = 'form';
                $this->addError('newEmail', 'Эта почта уже занята другим аккаунтом');

                return;
            }
            $oldEmail = (string) $user->email;
            $user->forceFill(['email' => $pending['value'], 'email_verified_at' => now()])->save();
            if ($oldEmail !== '') {
                Notification::route('mail', $oldEmail)->notify(new EmailChanged($pending['value']));
            }
            $message = 'Почта изменена — теперь входите с ' . $pending['value'];
        } else {
            $user->forceFill(['password' => $pending['value'], 'remember_token' => Str::random(60)])->save();
            // Выходим на других устройствах: сессии в базе (SESSION_DRIVER=database), текущую сохраняем
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)->where('id', '!=', session()->getId())->delete();
            }
            $message = 'Пароль изменён. На других устройствах нужно будет войти заново';
        }

        $this->reset('editing', 'step', 'newEmail', 'currentPassword', 'newPassword', 'verification_code', 'codeResent');
        $this->dispatch('toast', message: $message);
    }

    /** false — сервис почты отказал: в сессии остаётся прежняя смена (или ничего), прежний код в силе. */
    private function deliver(string $kind, string $value, string $to): bool
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if (! MailDelivery::attempt(fn () => Notification::route('mail', $to)->notify(new EmailVerificationCode($code, $kind)))) {
            return false;
        }

        session()->put(self::SESSION_KEY, [
            'user_id' => auth()->id(),
            'kind' => $kind,
            'value' => $value,
            'to' => $to,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES)->toIso8601String(),
            'attempts' => 0,
            'sent_at' => now()->toIso8601String(),
        ]);

        return true;
    }

    /** Не больше 5 писем с кодом за 10 минут на аккаунт. */
    private function throttleSend(string $field): bool
    {
        $key = 'account-change:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError($field, 'Слишком много писем подряд. Попробуйте через ' . plural_ru((int) ceil(RateLimiter::availableIn($key) / 60), 'минуту', 'минуты', 'минут') . '.');

            return false;
        }
        RateLimiter::hit($key, 600);

        return true;
    }

    /** Незавершённая смена этого пользователя, пока код действует. */
    private function pending(): ?array
    {
        $pending = session()->get(self::SESSION_KEY);
        if (! is_array($pending) || ($pending['user_id'] ?? null) !== auth()->id() || now()->greaterThan(Carbon::parse($pending['expires_at']))) {
            return null;
        }

        return $pending;
    }

    private function expired(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->step = 'form';
        $this->addError('code_expired', 'Код устарел — запросите новый.');
    }

    public function render()
    {
        $pending = $this->pending();

        return view('livewire.cabinet.account', [
            'user' => auth()->user(),
            'sentTo' => $pending['to'] ?? null,
            'ttl' => self::CODE_TTL_MINUTES,
        ]);
    }
}
