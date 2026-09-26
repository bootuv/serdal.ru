<?php

namespace App\Livewire\Auth;

use App\Http\Responses\LoginResponse;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Новый пароль по ссылке из письма. Устаревшая ссылка — экран «Ссылка устарела». Макет: AuthReset. */
#[Layout('components.layouts.auth', ['title' => 'Новый пароль'])]
class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    #[Locked]
    public string $email = '';

    #[Locked]
    public bool $expired = false;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');

        $user = $this->email !== '' ? Password::broker()->getUser(['email' => $this->email]) : null;
        $this->expired = ! $user || ! Password::broker()->tokenExists($user, $token);
    }

    public function save()
    {
        abort_if($this->expired, 403);

        $this->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required' => 'Придумайте пароль.',
            'password.min' => 'Пароль должен быть не короче 8 символов.',
            'password.confirmed' => 'Пароли не совпадают.',
        ]);

        $status = Password::reset(
            ['email' => $this->email, 'token' => $this->token, 'password' => $this->password, 'password_confirmation' => $this->password_confirmation],
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->expired = true;

            return null;
        }

        $user = User::where('email', $this->email)->first();
        if (! $user || $user->is_blocked) {
            return redirect()->route('login');
        }

        Auth::login($user, true);
        session()->regenerate();

        return (new LoginResponse)->toResponse(request());
    }

    public function render()
    {
        return view('livewire.auth.reset-password', [
            'expire' => (int) config('auth.passwords.users.expire', 60),
        ]);
    }
}
