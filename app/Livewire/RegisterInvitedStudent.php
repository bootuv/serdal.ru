<?php

namespace App\Livewire;

use App\Models\User;
use App\Notifications\EmailVerificationCode;
use App\Notifications\NewTeacher;
use App\Notifications\StudentAcceptedInvite;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Регистрация ученика по ссылке-приглашению учителя (/register/invite, подписанная ссылка).
 * Шаг 1 — анкета и согласие, шаг 2 — код из письма. Уже вошедший ученик сразу привязывается к учителю.
 */
#[Layout('components.layouts.auth', ['title' => 'Приглашение от учителя'])]
class RegisterInvitedStudent extends Component
{
    /** Сколько минут действует код из письма. */
    public const CODE_TTL_MINUTES = 30;

    public $first_name;
    public $last_name;
    public $middle_name;
    public $email;
    public $phone;
    public $password;
    public $password_confirmation;
    public bool $agree = false;

    #[Locked]
    public $teacher_id;

    public $step = 1;
    public $verification_code;

    /** Код отправлен повторно — показываем бейдж «Новый код отправлен». */
    public bool $codeResent = false;

    /** Почта уже зарегистрирована — под полем ссылка «Войти». */
    public bool $emailTaken = false;

    public function mount()
    {
        if (! request()->hasValidSignature()) {
            abort(403, 'Ссылка приглашения недействительна или устарела.');
        }

        $this->teacher_id = request()->query('teacher');

        if (Auth::check()) {
            $user = Auth::user();

            if ($this->teacher_id) {
                $teacher = User::find($this->teacher_id);
                if ($teacher) {
                    // Привязываем ученика к учителю
                    $changes = $teacher->students()->syncWithoutDetaching([$user->id]);

                    if (count($changes['attached']) > 0) {
                        // Учителю — «ученик принял приглашение», ученику — «новый учитель»
                        $teacher->notify(new StudentAcceptedInvite($user));
                        $user->notify(new NewTeacher($teacher));

                        session()->flash('toast', 'Вы добавлены в список учеников');
                    } else {
                        session()->flash('toast', 'Вы уже в списке учеников');
                    }
                }
            }

            return redirect()->route('cabinet.student.home');
        }
    }

    public function register()
    {
        $this->emailTaken = false;
        $this->resetErrorBag();

        if ($this->email && User::where('email', $this->email)->exists()) {
            $this->emailTaken = true;
            $this->addError('email', 'Этот email уже зарегистрирован.');

            return;
        }

        $this->validate([
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'agree' => ['accepted'],
        ], [
            'last_name.required' => 'Укажите фамилию',
            'first_name.required' => 'Укажите имя',
            'middle_name.required' => 'Укажите отчество',
            'email.required' => 'Укажите email',
            'email.email' => 'Проверьте адрес — в нём ошибка',
            'phone.max' => 'Слишком длинный номер',
            'password.required' => 'Придумайте пароль',
            'password.min' => 'Пароль — минимум 8 символов',
            'password.confirmed' => 'Пароли не совпадают',
            'agree.accepted' => 'Отметьте согласие, чтобы продолжить',
        ]);

        $code = $this->generateCode();

        // Данные анкеты ждут подтверждения почты в сессии
        session()->put('registration_data', [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'password' => $this->password,
            'teacher_id' => $this->teacher_id,
            'verification_code' => $code,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);

        Notification::route('mail', $this->email)->notify(new EmailVerificationCode($code));

        $this->verification_code = null;
        $this->codeResent = false;
        $this->step = 2;
    }

    public function verifyAndRegister()
    {
        $this->resetErrorBag();
        $data = session()->get('registration_data');

        if (! $data || now()->greaterThan($data['expires_at'])) {
            $this->step = 1;
            $this->addError('code_expired', 'Код устарел — запросите новый.');

            return;
        }

        $code = preg_replace('/\D/', '', (string) $this->verification_code);

        if (strlen($code) !== 6) {
            $this->addError('verification_code', 'Введите все 6 цифр из письма');

            return;
        }

        if ($code !== $data['verification_code']) {
            $this->addError('verification_code', 'Код не подходит — проверьте цифры в письме');

            return;
        }

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'middle_name' => $data['middle_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'role' => 'student',
        ]);
        // Почта подтверждена кодом (email_verified_at нет в $fillable — ставим напрямую)
        $user->forceFill(['email_verified_at' => now()])->save();

        // Привязываем ученика к учителю
        if ($data['teacher_id']) {
            $teacher = User::find($data['teacher_id']);
            if ($teacher) {
                $teacher->students()->syncWithoutDetaching([$user->id]);
                $teacher->notify(new StudentAcceptedInvite($user));
                $user->notify(new NewTeacher($teacher));
            }
        }

        event(new Registered($user));

        auth()->guard('web')->login($user);
        session()->forget('registration_data');
        session()->regenerate();

        return redirect()->route('cabinet.student.home');
    }

    public function resendCode()
    {
        $data = session()->get('registration_data');

        if (! $data) {
            $this->step = 1;
            $this->addError('code_expired', 'Код устарел — запросите новый.');

            return;
        }

        $code = $this->generateCode();
        $data['verification_code'] = $code;
        $data['expires_at'] = now()->addMinutes(self::CODE_TTL_MINUTES);
        session()->put('registration_data', $data);

        Notification::route('mail', $data['email'])->notify(new EmailVerificationCode($code));

        $this->resetErrorBag('verification_code');
        $this->codeResent = true;
    }

    public function backToForm()
    {
        $this->resetErrorBag();
        $this->codeResent = false;
        $this->step = 1;
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** «Мария Соколова» — имя и фамилия учителя для заголовка; null — учитель не найден. */
    private function teacherName(): ?string
    {
        $teacher = $this->teacher_id ? User::find($this->teacher_id) : null;
        if (! $teacher) {
            return null;
        }

        $short = trim($teacher->first_name . ' ' . $teacher->last_name);

        return $short !== '' ? $short : ($teacher->name ?: null);
    }

    public function render()
    {
        return view('livewire.auth.invite', [
            'teacherName' => $this->teacherName(),
            'ttl' => self::CODE_TTL_MINUTES,
        ]);
    }
}
