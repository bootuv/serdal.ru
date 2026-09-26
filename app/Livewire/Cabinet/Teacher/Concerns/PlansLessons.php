<?php

namespace App\Livewire\Cabinet\Teacher\Concerns;

use App\Models\LessonType;
use App\Models\Room;
use App\Models\User;
use App\Services\PaymentRecordService;
use App\Services\TeacherLessonService;
use App\Support\HumanDate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/**
 * Окно «Запланировать занятие» (макет LsPlan): ученики, название, дата и время, длительность, повтор по дням недели.
 * Создание — TeacherLessonService::create (как CreateRoom в старом кабинете). Разметка — partials/plan-modal.blade.php.
 */
trait PlansLessons
{
    public bool $planOpen = false;

    public string $planKind = 'individual';

    public string $planStudentId = '';

    /** @var array<int> ученики группового занятия */
    public array $planStudents = [];

    /** Выбор «Добавить ученика» в групповом занятии. */
    public string $planAdd = '';

    public string $planName = '';

    public string $planDate = '';

    public string $planTime = '';

    public int $planDuration = \App\Models\RoomSchedule::DEFAULT_DURATION;

    public string $planRepeat = 'weekly';

    /** @var array<int> дни недели (0 — вс) основного времени */
    public array $planDays = [];

    public string $planUntil = '';

    /** @var array<int, array{days: array<int>, time: string}> дополнительные дни и время (отдельные правила расписания) */
    public array $planSlots = [];

    public function openPlan(?int $studentId = null): void
    {
        $this->resetValidation();
        $teacher = auth()->user();
        $start = now()->addHour()->startOfHour();

        $this->planKind = 'individual';
        $this->planStudentId = $studentId ? (string) $studentId : '';
        $this->planStudents = [];
        $this->planAdd = '';
        $this->planName = '';
        $this->planDate = $start->format('Y-m-d');
        $this->planTime = $start->format('H:i');
        $this->planRepeat = 'weekly';
        $this->planDays = [$start->dayOfWeek];
        $this->planUntil = '';
        $this->planSlots = [];
        $this->planDuration = (int) ($teacher->lessonTypes()->where('type', LessonType::TYPE_INDIVIDUAL)->value('duration') ?: \App\Models\RoomSchedule::DEFAULT_DURATION);
        $this->planOpen = true;
    }

    public function closePlan(): void
    {
        $this->planOpen = false;
    }

    public function updatedPlanKind(): void
    {
        if (! in_array($this->planKind, ['individual', 'group'], true)) {
            $this->planKind = 'individual';
        }

        // Выбранный ученик переходит в группу и обратно
        if ($this->planKind === 'group' && $this->planStudentId !== '' && ! $this->planStudents) {
            $this->planStudents = [(int) $this->planStudentId];
        } elseif ($this->planKind === 'individual' && $this->planStudentId === '' && $this->planStudents) {
            $this->planStudentId = (string) $this->planStudents[0];
        }

        $duration = auth()->user()->lessonTypes()->where('type', $this->planKind)->value('duration');
        if ($duration) {
            $this->planDuration = (int) $duration;
        }
    }

    public function updatedPlanRepeat(): void
    {
        if (! in_array($this->planRepeat, ['once', 'weekly'], true)) {
            $this->planRepeat = 'once';
        }
    }

    public function updatedPlanAdd(): void
    {
        $id = (int) $this->planAdd;
        if ($id && ! in_array($id, $this->planStudents, true)) {
            $this->planStudents[] = $id;
        }
        $this->planAdd = '';
    }

    public function removePlanStudent(int $id): void
    {
        $this->planStudents = array_values(array_filter($this->planStudents, fn ($s) => (int) $s !== $id));
    }

    public function togglePlanDay(int $day, ?int $slot = null): void
    {
        if ($day < 0 || $day > 6) {
            return;
        }

        $days = $slot === null ? $this->planDays : ($this->planSlots[$slot]['days'] ?? []);
        $days = in_array($day, $days, true) ? array_values(array_diff($days, [$day])) : [...$days, $day];

        if ($slot === null) {
            $this->planDays = $days;
        } elseif (isset($this->planSlots[$slot])) {
            $this->planSlots[$slot]['days'] = $days;
        }
    }

    public function addPlanSlot(): void
    {
        $this->planSlots[] = ['days' => [], 'time' => $this->planTime];
    }

    public function removePlanSlot(int $index): void
    {
        unset($this->planSlots[$index]);
        $this->planSlots = array_values($this->planSlots);
    }

    public function savePlan(): ?Room
    {
        $teacher = auth()->user();
        $service = app(TeacherLessonService::class);
        $allowedIds = $service->studentsQuery($teacher)->pluck('users.id')->all();
        $weekly = $this->planRepeat === 'weekly';

        $this->validate([
            'planName' => ['required', 'string', 'max:255'],
            'planStudentId' => [Rule::requiredIf($this->planKind === 'individual'), 'nullable', Rule::in($allowedIds)],
            'planStudents' => [Rule::requiredIf($this->planKind === 'group'), 'array'],
            'planStudents.*' => [Rule::in($allowedIds)],
            'planDate' => ['required', 'date'],
            'planTime' => ['required', 'date_format:H:i'],
            'planDuration' => ['required', 'integer', 'min:1', 'max:1440'],
            'planDays' => [Rule::requiredIf($weekly), 'array'],
            'planDays.*' => ['integer', 'between:0,6'],
            'planUntil' => ['nullable', 'date', 'after_or_equal:planDate'],
            'planSlots.*.days' => [Rule::requiredIf($weekly), 'array'],
            'planSlots.*.time' => [Rule::requiredIf($weekly), 'nullable', 'date_format:H:i'],
        ], [
            'planName.required' => 'Назовите занятие: предмет или тему',
            'planStudentId.required' => 'Выберите ученика',
            'planStudents.required' => 'Добавьте учеников в группу',
            'planDate.required' => 'Укажите дату',
            'planTime.required' => 'Укажите время',
            'planTime.date_format' => 'Время в формате 17:00',
            'planDays.required' => 'Выберите дни недели',
            'planUntil.after_or_equal' => 'Дата окончания раньше первого занятия',
            'planSlots.*.days.required' => 'Выберите дни недели',
            'planSlots.*.time.required' => 'Укажите время',
        ]);

        $participants = $this->planKind === 'group' ? $this->planStudents : [(int) $this->planStudentId];

        $schedules = [];
        if ($weekly) {
            foreach ([['days' => $this->planDays, 'time' => $this->planTime], ...$this->planSlots] as $slot) {
                $schedules[] = TeacherLessonService::scheduleAttributes('weekly', $this->planDate, $slot['time'], $this->planDuration, $slot['days'], $this->planUntil ?: null);
            }
        } else {
            $schedules[] = TeacherLessonService::scheduleAttributes('once', $this->planDate, $this->planTime, $this->planDuration);
        }

        $room = $service->create($teacher, trim($this->planName), $participants, $schedules);

        $this->planOpen = false;
        $this->dispatch('toast', message: count($participants) > 1 ? 'Занятие запланировано, ученики получат уведомление' : 'Занятие запланировано, ученик получит уведомление');

        return $room;
    }

    /** Данные для разметки окна. */
    protected function planView(User $teacher): array
    {
        if (! $this->planOpen) {
            return [];
        }

        $students = app(TeacherLessonService::class)->studentsQuery($teacher)->orderBy('name')->pluck('name', 'id');
        $lessonType = $teacher->lessonTypes()->where('type', $this->planKind)->first();
        $first = TeacherLessonService::firstOccurrence($this->planRepeat, $this->planDate, $this->planTime, $this->planDays);
        $tariff = $teacher->activeSubscription()?->tariff;
        $count = count($this->planStudents);

        return [
            'planStudentOptions' => $students->all(),
            'planGroupOptions' => $students->except($this->planStudents)->all(),
            'planChosen' => collect($this->planStudents)->map(fn ($id) => ['id' => (int) $id, 'name' => $students[$id] ?? ''])->all(),
            'planWhoHint' => $this->planKind === 'group' && $count
                ? plural_ru($count, 'ученик', 'ученика', 'учеников') . ($tariff?->max_participants ? ' · на «' . $tariff->name . '» до ' . $tariff->max_participants . ' в занятии' : '')
                : 'Нового ученика сначала пригласите в разделе «Ученики»',
            'planPrice' => self::priceNote($lessonType),
            'planPricesUrl' => Route::has('cabinet.teacher.profile') ? route('cabinet.teacher.profile') : url('/tutor/lesson-types'),
            'planFirst' => $first ? ($this->planRepeat === 'weekly' ? 'Первое занятие — ' : 'Разовое занятие — ') . HumanDate::at($first) : null,
            'planDurations' => TeacherLessonService::durationOptions($this->planDuration),
        ];
    }

    /** Цена и срок оплаты по базовым ценам учителя (как начисляет PaymentRecordService). */
    public static function priceNote(?LessonType $lessonType): array
    {
        if (! $lessonType || ! $lessonType->price) {
            return ['main' => 'Цена не указана', 'sub' => 'Укажите цену занятия, чтобы вести учёт оплаты.', 'link' => 'Указать цены'];
        }

        $price = \App\Support\Money::format((int) $lessonType->price);

        if ($lessonType->isMonthly()) {
            $day = $lessonType->payment_due_day ?: PaymentRecordService::MONTHLY_DUE_DAY;

            return ['main' => $price . ' в месяц с каждого ученика · оплата до ' . $day . ' числа', 'sub' => 'Начисляем каждому ученику отдельно.', 'link' => 'Изменить цены'];
        }

        $days = $lessonType->payment_due_days ?: PaymentRecordService::PER_LESSON_DUE_DAYS;

        return ['main' => $price . ' за занятие · оплата в течение ' . plural_ru($days, 'дня', 'дней', 'дней'), 'sub' => 'Начисляем, только если ученик был на занятии.', 'link' => 'Изменить цены'];
    }
}
