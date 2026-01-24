<?php

namespace App\Livewire;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Auth\Events\Registered;
use Livewire\Attributes\Locked;

class RegisterInvitedStudent extends Component
{
    public $first_name;
    public $last_name;
    public $middle_name;
    public $email;
    public $phone;
    public $password;
    public $password_confirmation;
    #[Locked]
    public $teacher_id;

    public $step = 1;
    public $verification_code;

    public function mount()
    {
        if (!request()->hasValidSignature()) {
            abort(403, 'Ссылка приглашения недействительна или устарела.');
        }

        $this->teacher_id = request()->query('teacher');

        if (Auth::check()) {
            $user = Auth::user();

            if ($this->teacher_id) {
                $teacher = User::find($this->teacher_id);
                if ($teacher) {
                    // Attach the student to the teacher
                    $changes = $teacher->students()->syncWithoutDetaching([$user->id]);

                    if (count($changes['attached']) > 0) {
                        // Notify teacher about accepted invite
                        $teacher->notify(new \App\Notifications\StudentAcceptedInvite($user));

                        // Notify student about new teacher
                        $user->notify(new \App\Notifications\NewTeacher($teacher));

                        Notification::make()
                            ->title('Вы добавлены в список учеников')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Вы уже находитесь в списке учеников')
                            ->info()
                            ->send();
                    }
                }
            }

            return redirect()->to('/student');
        }
    }

    public function register()
    {
        if (User::where('email', $this->email)->exists()) {
            $loginUrl = route('login');
            $this->addError('email', "Этот Email уже используется. <a href='{$loginUrl}' class='font-bold underline hover:text-amber-800'>Войти в аккаунт?</a>");
            return;
        }

        $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Генерируем код
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Сохраняем данные в сессию
        session()->put('registration_data', [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'password' => $this->password,
            'teacher_id' => $this->teacher_id,
            'verification_code' => $code,
            'expires_at' => now()->addMinutes(30),
        ]);

        // Отправляем код
        \Illuminate\Support\Facades\Notification::route('mail', $this->email)
            ->notify(new \App\Notifications\EmailVerificationCode($code));

        $this->step = 2;
    }

    public function verifyAndRegister()
    {
        $data = session()->get('registration_data');

        if (!$data || now()->greaterThan($data['expires_at'])) {
            $this->step = 1;
            $this->addError('verification_code', 'Срок действия кода истек. Пожалуйста, заполните форму заново.');
            return;
        }

        if ($this->verification_code !== $data['verification_code']) {
            $this->addError('verification_code', 'Неверный код подтверждения.');
            return;
        }

        // Создаем пользователя
        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'middle_name' => $data['middle_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        // Привязываем ученика к учителю
        if ($data['teacher_id']) {
            $teacher = User::find($data['teacher_id']);
            if ($teacher) {
                $teacher->students()->syncWithoutDetaching([$user->id]);
                $teacher->notify(new \App\Notifications\StudentAcceptedInvite($user));
                $user->notify(new \App\Notifications\NewTeacher($teacher));
            }
        }

        event(new Registered($user));

        auth()->guard('web')->login($user);
        session()->forget('registration_data');
        session()->regenerate();

        return redirect()->to('/student');
    }

    public function resendCode()
    {
        $data = session()->get('registration_data');

        if (!$data) {
            $this->step = 1;
            return;
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $data['verification_code'] = $code;
        $data['expires_at'] = now()->addMinutes(30);
        session()->put('registration_data', $data);

        \Illuminate\Support\Facades\Notification::route('mail', $data['email'])
            ->notify(new \App\Notifications\EmailVerificationCode($code));

        Notification::make()
            ->title('Код отправлен повторно')
            ->success()
            ->send();
    }

    public function backToForm()
    {
        $this->step = 1;
    }

    #[Layout('components.layouts.app')]
    public function render()
    {
        return view('livewire.register-invited-student');
    }
}
