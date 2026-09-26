<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\Direct;
use App\Models\LessonType;
use App\Models\Tariff;
use App\Models\User;
use App\Services\SubscriptionCheckoutService;
use App\Services\TeacherOnboardingService;
use App\Services\TeacherProfileService;
use App\Services\TeacherStudentsService;
use App\Services\YooKassaService;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Первые шаги учителя: Профиль → Цены → Тариф → «Готово». Макеты: «Онбординг · 1–4».
 * Шаги и сохранение — как в старом кабинете (Filament App\Pages\Onboarding, TeacherOnboardingService);
 * направления и классы перенесены сюда из заявки.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Первые шаги', 'active' => null])]
class Onboarding extends Component
{
    use TeacherScreen;
    use WithFileUploads;

    /** Классы на шаге «Профиль» — группами, как в макете. */
    public const GRADE_GROUPS = [
        'preschool' => ['Дошкольники', ['preschool']],
        'primary' => ['1–4 класс', ['1', '2', '3', '4']],
        'middle' => ['5–9 класс', ['5', '6', '7', '8', '9']],
        'senior' => ['10–11 класс', ['10', '11']],
        'adults' => ['Взрослые', ['adults']],
    ];

    public int $step = 1;
    public int $reached = 1;
    public bool $done = false;

    // Профиль
    public $photo = null;
    public bool $removePhoto = false;
    public array $directs = [];
    public array $grades = [];
    public string $whatsup = '';
    public string $telegram = '';

    // Цены
    public array $lessonTypes = [];

    // Тариф
    public string $billingPeriod = 'month';
    public ?int $tariffId = null;
    public string $payMethod = 'sbp';

    /** Итог: тариф не оплачен сразу (оплата не настроена или платёж не создался). */
    public ?string $doneNote = null;

    public function mount()
    {
        $user = $this->authorizeTeacher(false);

        // Настройка уже пройдена — в кабинет
        if ($user->is_profile_completed) {
            return $this->redirect($this->cabinetUrl());
        }

        $this->directs = $user->directs()->pluck('directs.id')->map(fn ($id) => (int) $id)->all();
        $this->grades = TeacherProfileService::gradesForForm($user->grade);
        $this->whatsup = (string) $user->whatsup;
        $this->telegram = (string) $user->telegram;
        $this->lessonTypes = array_map(fn (array $row) => [
            'type' => $row['type'],
            'payment_type' => $row['payment_type'] ?: LessonType::PAYMENT_PER_LESSON,
            'price' => $row['price'] !== null ? (string) (int) $row['price'] : '',
            'duration' => $row['duration'] ?? 60,
            'count_per_week' => $row['count_per_week'] ?? '',
        ], TeacherOnboardingService::lessonTypesForForm($user));
        $this->tariffId = TeacherOnboardingService::defaultTariffId($user);
    }

    public function updatedPhoto(): void
    {
        $this->removePhoto = false;
        $this->validateOnly('photo', ['photo' => ['nullable', 'image']], [], ['photo' => 'фото']);
    }

    public function deletePhoto(): void
    {
        $this->photo = null;
        $this->removePhoto = true;
    }

    public function toggleDirect(int $id): void
    {
        $this->directs = in_array($id, $this->directs, true)
            ? array_values(array_diff($this->directs, [$id]))
            : [...$this->directs, $id];
    }

    public function toggleGradeGroup(string $key): void
    {
        $members = self::GRADE_GROUPS[$key][1] ?? null;
        if (! $members) {
            return;
        }

        $this->grades = empty(array_diff($members, $this->grades))
            ? array_values(array_diff($this->grades, $members))
            : TeacherProfileService::gradesForForm([...$this->grades, ...$members]);
    }

    /** Как в старом кабинете: смена типа занятий меняет способ оплаты (групповые — за месяц). */
    public function updatedLessonTypes($value, string $key): void
    {
        if (str_ends_with($key, '.type')) {
            $i = (int) explode('.', $key)[0];
            $this->lessonTypes[$i]['payment_type'] = $value === LessonType::TYPE_GROUP ? LessonType::PAYMENT_MONTHLY : LessonType::PAYMENT_PER_LESSON;
        }
    }

    public function addLessonType(): void
    {
        if (count($this->lessonTypes) >= 2) {
            return;
        }

        $other = ($this->lessonTypes[0]['type'] ?? null) === LessonType::TYPE_GROUP ? LessonType::TYPE_INDIVIDUAL : LessonType::TYPE_GROUP;
        $this->lessonTypes[] = [
            'type' => $other,
            'payment_type' => $other === LessonType::TYPE_GROUP ? LessonType::PAYMENT_MONTHLY : LessonType::PAYMENT_PER_LESSON,
            'price' => '',
            'duration' => 60,
            'count_per_week' => '',
        ];
    }

    /** Единственную цену удалить нельзя — шаг не должен оставаться пустым. */
    public function removeLessonType(int $index): void
    {
        if (count($this->lessonTypes) > 1) {
            unset($this->lessonTypes[$index]);
            $this->lessonTypes = array_values($this->lessonTypes);
            $this->resetValidation();
        }
    }

    public function next(): void
    {
        $this->validateStep($this->step);
        $this->step = min(3, $this->step + 1);
        $this->reached = max($this->reached, $this->step);
    }

    /** «Пропустить» на шаге «Профиль»: всё на нём необязательно. */
    public function skip(): void
    {
        if ($this->step === 1) {
            $this->step = 2;
            $this->reached = max($this->reached, 2);
        }
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function goTo(int $step): void
    {
        if ($step >= 1 && $step <= $this->reached) {
            $this->step = $step;
        }
    }

    public function restart(): void
    {
        $this->done = false;
        $this->doneNote = null;
        $this->step = 1;
    }

    public function finish()
    {
        foreach ([1, 2, 3] as $step) {
            try {
                $this->validateStep($step);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->step = $step;
                throw $e;
            }
        }

        $user = auth()->user();
        $data = [
            'lesson_types' => array_map(fn (array $row) => [
                'type' => $row['type'],
                'payment_type' => $row['payment_type'],
                'price' => $row['price'],
                'duration' => $row['duration'],
                'count_per_week' => $row['count_per_week'] !== '' ? $row['count_per_week'] : null,
            ], $this->lessonTypes),
            'avatar' => $this->photo ?: ($this->removePhoto ? null : $user->avatar),
            'whatsup' => $this->whatsup !== '' ? $this->whatsup : null,
            'telegram' => ltrim(trim($this->telegram), '@') !== '' ? ltrim(trim($this->telegram), '@') : null,
            'directs' => $this->directs,
            'grade' => $this->grades,
            'tariff_id' => $this->tariffId,
            'billing_period' => $this->billingPeriod,
            'payment_method' => $this->payMethod,
        ];

        $result = app(TeacherOnboardingService::class)->complete($user, $data);

        // Выбран платный тариф — сразу уводим на платёжную страницу
        if ($result['payment_url']) {
            return $this->redirect($result['payment_url']);
        }

        $tariff = $result['tariff'];
        $this->doneNote = match (true) {
            $result['payment_failed'] => 'Не удалось создать платёж — оплатить тариф «' . $tariff->name . '» можно в любой момент в разделе «Тариф и платежи».',
            $tariff && ! $tariff->isFree() => 'Онлайн-оплата подключается — тариф «' . $tariff->name . '» можно будет оплатить позже в разделе «Тариф и платежи».',
            default => null,
        };
        $this->reset('photo', 'removePhoto');
        $this->done = true;

        return null;
    }

    private function validateStep(int $step): void
    {
        $attributes = [
            'photo' => 'фото',
            'whatsup' => 'WhatsApp',
            'telegram' => 'Telegram',
            'lessonTypes.*.type' => 'тип занятий',
            'lessonTypes.*.payment_type' => 'способ оплаты',
            'lessonTypes.*.price' => 'цена',
            'lessonTypes.*.duration' => 'длительность',
            'lessonTypes.*.count_per_week' => 'занятий в неделю',
            'tariffId' => 'тариф',
        ];

        $rules = match ($step) {
            1 => [
                'photo' => ['nullable', 'image'],
                'directs' => ['array'],
                'directs.*' => ['integer', Rule::exists('directs', 'id')],
                'grades' => ['array'],
                'grades.*' => [Rule::in(array_map('strval', array_keys(TeacherProfileService::GRADES)))],
                'whatsup' => ['nullable', 'string', 'max:255'],
                'telegram' => ['nullable', 'string', 'max:255'],
            ],
            2 => [
                'lessonTypes' => ['required', 'array', 'min:1', 'max:2'],
                'lessonTypes.*.type' => ['required', 'distinct', Rule::in(array_keys(LessonType::TYPES))],
                'lessonTypes.*.payment_type' => ['required', Rule::in([LessonType::PAYMENT_PER_LESSON, LessonType::PAYMENT_MONTHLY])],
                'lessonTypes.*.price' => ['required', 'numeric', 'min:1'],
                'lessonTypes.*.duration' => ['required', 'numeric', 'min:15'],
                'lessonTypes.*.count_per_week' => ['nullable', 'required_if:lessonTypes.*.payment_type,' . LessonType::PAYMENT_MONTHLY, 'numeric', 'min:1'],
            ],
            default => [
                'tariffId' => ['required', Rule::exists('tariffs', 'id')->where('is_active', true)->whereNull('deleted_at')],
                'billingPeriod' => ['required', Rule::in(['month', 'year'])],
            ],
        };

        $this->validate($rules, [
            'lessonTypes.*.type.distinct' => 'Для каждого типа занятий — одна цена',
            'lessonTypes.*.count_per_week.required_if' => 'Укажите, сколько занятий в неделю',
        ], $attributes);
    }

    private function cabinetUrl(): string
    {
        return Route::has('cabinet.teacher.today') ? route('cabinet.teacher.today') : url('/tutor');
    }

    public function render()
    {
        $user = auth()->user();
        $tariffs = Tariff::active()->get();
        $selected = $tariffs->firstWhere('id', $this->tariffId);
        $yearly = $this->billingPeriod === 'year' && $selected?->hasYearly();

        return view('livewire.cabinet.teacher.onboarding', [
            'user' => $user,
            'firstName' => $user->first_name ?: $user->name,
            'steps' => [
                1 => ['Профиль', 'Всё необязательно — можно заполнить позже.'],
                2 => ['Цены для учеников', 'По этим ценам считаются оплаты учеников.'],
                3 => ['Тариф', 'Начать можно бесплатно, сменить тариф — в любой момент.'],
            ],
            'directOptions' => Direct::orderBy('name')->pluck('name', 'id'),
            'gradeGroups' => self::GRADE_GROUPS,
            'tariffs' => $tariffs,
            'maxDiscount' => $tariffs->max(fn (Tariff $t) => $t->yearlyDiscountPercent()),
            'selected' => $selected,
            'freeName' => $tariffs->first(fn (Tariff $t) => $t->isFree())?->name ?? 'Старт',
            'payable' => $selected && ! $selected->isFree(),
            'configured' => YooKassaService::isConfigured(),
            'payAmount' => $selected && ! $selected->isFree()
                ? number_format($yearly ? $selected->yearly_price : $selected->price, 0, ',', ' ') . ' ₽ за ' . ($yearly ? 'год' : plural_ru((int) $selected->period_days, 'день', 'дня', 'дней'))
                : null,
            'methods' => SubscriptionCheckoutService::paymentMethods(),
            'inviteUrl' => $this->done ? app(TeacherStudentsService::class)->invitationLink($user) : null,
            'cabinetUrl' => $this->cabinetUrl(),
        ]);
    }
}
