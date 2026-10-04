<?php

namespace App\Livewire\Auth;

use App\Support\MailDelivery;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Восстановление пароля: письмо со ссылкой на /reset-password/{token}. Макет: AuthForgot. */
#[Layout('components.layouts.auth', ['title' => 'Восстановление пароля'])]
class ForgotPassword extends Component
{
    public string $email = '';

    /** Письмо отправлено (или адреса нет — не выдаём, есть ли такой аккаунт). */
    #[Locked]
    public bool $sent = false;

    /** Сколько раз отправляли — перезапускает отсчёт до повторной отправки. */
    #[Locked]
    public int $round = 0;

    public function mount(): void
    {
        $this->email = (string) request()->query('email', '');
    }

    public function send(): void
    {
        $this->validate(['email' => ['required', 'email']], [
            'email.required' => 'Введите почту.',
            'email.email' => 'Проверьте почту — в ней ошибка.',
        ]);

        $status = null;
        if (! MailDelivery::attempt(function () use (&$status) {
            $status = Password::sendResetLink(['email' => $this->email]);
        })) {
            $this->addError('email', MailDelivery::FAILED);

            return;
        }

        if ($status === Password::RESET_THROTTLED) {
            $this->addError('email', 'Письмо уже отправлено — подождите минуту и попробуйте ещё раз.');

            return;
        }

        $this->sent = true;
        $this->round++;
    }

    public function back(): void
    {
        $this->sent = false;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.auth.forgot-password', [
            'expire' => (int) config('auth.passwords.users.expire', 60),
            'throttle' => (int) config('auth.passwords.users.throttle', 60),
        ]);
    }
}
