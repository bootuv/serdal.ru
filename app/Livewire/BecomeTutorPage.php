<?php

namespace App\Livewire;

use App\Jobs\SendTeacherApplicationTelegramNotification;
use App\Livewire\Cabinet\Teacher\Onboarding;
use App\Mail\NewTeacherApplicationMail;
use App\Models\Direct;
use App\Models\Subject;
use App\Models\Tariff;
use App\Models\TeacherApplication;
use App\Models\User;
use App\Notifications\TeacherApplicationReceived;
use App\Services\ReferralService;
use App\Services\TeacherProfileService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Заявка учителя (/application): анкета → «Заявка отправлена».
 * Заявка уходит админам (уведомление, письмо, Telegram); после одобрения учитель получает доступ в кабинет.
 */
#[Layout('components.layouts.auth', ['title' => 'Заявка учителя', 'index' => true])]
class BecomeTutorPage extends Component
{
    /** Поля анкеты (ключи совпадают с колонками teacher_applications). */
    public array $data = [];

    public bool $isSubmitted = false;

    /** Почта уже зарегистрирована — под полем ссылка «Войти в кабинет». */
    public bool $emailTaken = false;

    /** Имя и почта отправленной заявки — для экрана «Заявка отправлена». */
    public ?string $sentName = null;
    public ?string $sentEmail = null;

    /** Тариф, выбранный на странице тарифов (?tariff=slug) — сохраняется в заявку. */
    public ?int $desiredTariffId = null;

    /** Реферальный код пригласившего учителя (из cookie ссылки /r/{code} или ?ref=). */
    #[Locked]
    public ?string $referralCode = null;

    public function mount(): void
    {
        if ($slug = request('tariff')) {
            $this->desiredTariffId = Tariff::active()->where('slug', $slug)->value('id');
        }

        $this->referralCode = request('ref') ?: request()->cookie(ReferralService::COOKIE);

        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->data = [
            'last_name' => '',
            'first_name' => '',
            'middle_name' => '',
            'email' => '',
            'phone' => '',
            'subjects' => [],
            'directs' => [],
            'grade' => [],
            'about' => '',
        ];
    }

    /** Пригласивший учитель — показываем плашку «Вас пригласил…». */
    public function referrer(): ?User
    {
        return ReferralService::enabled()
            ? ReferralService::findReferrer($this->referralCode)
            : null;
    }

    public function desiredTariff(): ?Tariff
    {
        return $this->desiredTariffId ? Tariff::find($this->desiredTariffId) : null;
    }

    /** Чипы «Что вы преподаёте» и «Направления»: выбрать / снять. */
    public function toggle(string $field, int $id): void
    {
        if (! in_array($field, ['subjects', 'directs'], true)) {
            return;
        }

        $current = array_map('intval', (array) ($this->data[$field] ?? []));
        $this->data[$field] = in_array($id, $current, true)
            ? array_values(array_diff($current, [$id]))
            : [...$current, $id];
        $this->resetErrorBag('data.' . $field);
    }

    /** Чипы «С кем занимаетесь» — группы классов, как в первых шагах кабинета. */
    public function toggleGradeGroup(string $key): void
    {
        $members = Onboarding::GRADE_GROUPS[$key][1] ?? null;
        if (! $members) {
            return;
        }

        $grades = array_map('strval', (array) ($this->data['grade'] ?? []));
        $this->data['grade'] = empty(array_diff($members, $grades))
            ? array_values(array_diff($grades, $members))
            : TeacherProfileService::gradesForForm([...$grades, ...$members]);
        $this->resetErrorBag('data.grade');
    }

    protected function rules(): array
    {
        return [
            'data.last_name' => ['required', 'string', 'max:255'],
            'data.first_name' => ['required', 'string', 'max:255'],
            'data.middle_name' => ['required', 'string', 'max:255'],
            'data.email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email'),
                function (string $attribute, $value, \Closure $fail) {
                    if (TeacherApplication::where('email', $value)->where('status', 'pending')->exists()) {
                        $fail('Ваша заявка уже отправлена и находится на рассмотрении.');
                    }
                },
            ],
            // Телефон: цифры, пробелы, скобки, дефисы, «+» в начале
            'data.phone' => ['required', 'string', 'max:255', 'regex:/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/'],
            'data.subjects' => ['required', 'array', 'min:1'],
            'data.subjects.*' => ['integer', Rule::exists('subjects', 'id')],
            // Направления обязательны, только если они заведены в справочнике
            'data.directs' => Direct::query()->exists() ? ['required', 'array', 'min:1'] : ['array'],
            'data.directs.*' => ['integer', Rule::exists('directs', 'id')],
            'data.grade' => ['required', 'array', 'min:1'],
            'data.grade.*' => [Rule::in(array_map('strval', array_keys(TeacherProfileService::GRADES)))],
            'data.about' => ['required', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'data.last_name.required' => 'Укажите фамилию',
            'data.first_name.required' => 'Укажите имя',
            'data.middle_name.required' => 'Укажите отчество',
            'data.email.required' => 'Укажите почту',
            'data.email.email' => 'Проверьте адрес — в нём ошибка',
            'data.email.unique' => 'Пользователь с такой почтой уже зарегистрирован.',
            'data.phone.required' => 'Укажите телефон',
            'data.phone.regex' => 'Проверьте номер — только цифры, пробелы, скобки и дефисы',
            'data.subjects.required' => 'Выберите хотя бы один предмет',
            'data.subjects.min' => 'Выберите хотя бы один предмет',
            'data.subjects.*' => 'Выберите предмет из списка',
            'data.directs.required' => 'Выберите хотя бы одно направление',
            'data.directs.min' => 'Выберите хотя бы одно направление',
            'data.directs.*' => 'Выберите направление из списка',
            'data.grade.required' => 'Выберите, с кем занимаетесь',
            'data.grade.min' => 'Выберите, с кем занимаетесь',
            'data.grade.*' => 'Выберите классы из списка',
            'data.about.required' => 'Расскажите пару слов о себе',
        ];
    }

    public function create(): void
    {
        $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $this->data);
        $this->data = $data;
        $this->emailTaken = filled($data['email'] ?? null) && User::where('email', $data['email'])->exists();

        $validated = $this->validate()['data'];
        $validated['subjects'] = array_map('intval', $validated['subjects']);
        $validated['directs'] = array_map('intval', $validated['directs']);
        $validated['grade'] = TeacherProfileService::gradesForForm($validated['grade']);

        $referrer = ReferralService::referrerForApplication($this->referralCode, $validated['email'] ?? null);

        $application = TeacherApplication::create($validated + [
            'desired_tariff_id' => $this->desiredTariffId,
            'referred_by_id' => $referrer?->id,
        ]);

        // Telegram-уведомление в чат техслужбы
        SendTeacherApplicationTelegramNotification::dispatch($application);

        // Уведомление администраторам: в админке и письмом
        $admins = User::where('role', User::ROLE_ADMIN)->get();

        foreach ($admins as $admin) {
            $admin->notify(new TeacherApplicationReceived($application));

            try {
                Mail::to($admin->email)->send(new NewTeacherApplicationMail($application));
            } catch (\Exception $e) {
                Log::error('Ошибка отправки уведомления администратору (' . $admin->email . '): ' . $e->getMessage());
            }
        }

        $this->sentName = $application->first_name;
        $this->sentEmail = $application->email;
        $this->isSubmitted = true;
        $this->resetForm();
    }

    public function render()
    {
        $referrer = $this->isSubmitted ? null : $this->referrer();

        return view('livewire.auth.application', [
            'subjectOptions' => $this->isSubmitted ? collect() : Subject::orderBy('name')->pluck('name', 'id'),
            'directOptions' => $this->isSubmitted ? collect() : Direct::orderBy('name')->pluck('name', 'id'),
            'gradeGroups' => Onboarding::GRADE_GROUPS,
            'referrer' => $referrer,
            'referralBonus' => $referrer ? ReferralService::referredBonus() : 0,
            'tariff' => $this->isSubmitted ? null : $this->desiredTariff(),
        ]);
    }
}
