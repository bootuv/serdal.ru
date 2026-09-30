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

    /** Сколько раз можно ошибиться в коде, прежде чем он сгорит. */
    private const MAX_CODE_ATTEMPTS = 5;

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

    /** Вход с возвратом на это приглашение. */
    #[Locked]
    public string $loginUrl = '';

    public function mount()
    {
        if (! request()->hasValidSignature()) {
            abort(403, 'Ссылка приглашения недействительна или устарела.');
        }

        $this->teacher_id = request()->query('teacher');

        if (Auth::check()) {
            return $this->attachSignedInUser(Auth::user());
        }

        // «Войти» ведёт на вход с возвратом сюда: после входа ученик вернётся по приглашению и будет привязан к учителю
        $this->loginUrl = route('login', ['next' => request()->getRequestUri()]);
    }

    /** Ссылку открыл уже вошедший пользователь: ученика привязываем к учителю и ведём в кабинет, остальным — объяснение. */
    private function attachSignedInUser(User $user)
    {
        // Приглашение — только для учеников: учитель или админ по ссылке учеником не становится
        if ($user->role !== User::ROLE_STUDENT) {
            session()->flash('error', 'Это приглашение для ученика. Откройте ссылку, выйдя из своего аккаунта, или отправьте её ученику.');

            return redirect(\App\Http\Middleware\EnsureCabinetRole::homeFor($user));
        }

        $teacher = $this->teacher_id ? User::whereKey($this->teacher_id)->where('role', User::ROLE_TUTOR)->first() : null;
        if (! $teacher) {
            session()->flash('error', 'Не нашли учителя по этой ссылке. Попросите у него новое приглашение.');

            return redirect()->route('cabinet.student.home');
        }

        $name = $this->teacherName() ?? 'Учитель';
        $changes = $teacher->students()->syncWithoutDetaching([$user->id]);

        if (count($changes['attached']) > 0) {
            // Учителю — «ученик принял приглашение», ученику — «новый учитель»
            $teacher->notify(new StudentAcceptedInvite($user));
            $user->notify(new NewTeacher($teacher));

            session()->flash('toast', $name . ' — теперь ваш учитель');
        } else {
            session()->flash('toast', $name . ' — уже ваш учитель');
        }

        return redirect()->route('cabinet.student.home');
    }

    public function register()
    {
        $this->emailTaken = false;
        $this->resetErrorBag();

        if ($this->email && User::where('email', $this->email)->exists()) {
            $this->emailTaken = true;
            $this->addError('email', 'Эта почта уже зарегистрирована.');

            return;
        }

        $this->validate([
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'agree' => ['accepted'],
        ], [
            'last_name.required' => 'Укажите фамилию',
            'first_name.required' => 'Укажите имя',
            'email.required' => 'Укажите почту',
            'email.email' => 'Проверьте адрес — в нём ошибка',
            'phone.max' => 'Слишком длинный номер',
            'password.required' => 'Придумайте пароль для Serdal',
            'password.min' => 'Пароль — минимум 8 символов',
            'password.confirmed' => 'Пароли не совпадают',
            'agree.accepted' => 'Отметьте согласие, чтобы продолжить',
        ]);

        // Не больше 5 писем с кодом за 10 минут с одного адреса
        $sendKey = 'invite-code:' . request()->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($sendKey, 5)) {
            $this->addError('email', 'Слишком много попыток. Попробуйте через ' . plural_ru((int) ceil(\Illuminate\Support\Facades\RateLimiter::availableIn($sendKey) / 60), 'минуту', 'минуты', 'минут') . '.');

            return;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($sendKey, 600);

        $code = $this->generateCode();

        // Данные анкеты ждут подтверждения почты в сессии
        session()->put('registration_data', [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => filled($this->middle_name) ? trim($this->middle_name) : null,
            'email' => $this->email,
            'phone' => $this->phone,
            // В сессии — только хеш пароля
            'password_hash' => Hash::make($this->password),
            'teacher_id' => $this->teacher_id,
            'verification_code' => $code,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'attempts' => 0,
            'sent_at' => now()->toIso8601String(),
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
            // После 5 неверных попыток код сгорает — нужен новый
            $data['attempts'] = ($data['attempts'] ?? 0) + 1;
            if ($data['attempts'] >= self::MAX_CODE_ATTEMPTS) {
                $data['expires_at'] = now()->subSecond();
                session()->put('registration_data', $data);
                $this->addError('verification_code', 'Слишком много неверных попыток — запросите новый код.');

                return;
            }
            session()->put('registration_data', $data);
            $this->addError('verification_code', 'Код не подходит — проверьте цифры в письме');

            return;
        }

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'middle_name' => filled($data['middle_name'] ?? null) ? $data['middle_name'] : null,
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password_hash'] ?? Hash::make($data['password']),
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

        // Повторно — не чаще раза в минуту
        if (isset($data['sent_at']) && now()->lt(\Illuminate\Support\Carbon::parse($data['sent_at'])->addMinute())) {
            $this->addError('verification_code', 'Новый код можно запросить через минуту после предыдущего.');

            return;
        }

        $code = $this->generateCode();
        $data['verification_code'] = $code;
        $data['expires_at'] = now()->addMinutes(self::CODE_TTL_MINUTES);
        $data['attempts'] = 0;
        $data['sent_at'] = now()->toIso8601String();
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
