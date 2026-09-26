<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\LessonRows;
use App\Livewire\Cabinet\Teacher\Concerns\MarksPayments;
use App\Livewire\Cabinet\Teacher\Concerns\PlansLessons;
use App\Livewire\Cabinet\Teacher\Concerns\StartsLessons;
use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\MeetingSession;
use App\Models\Message;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\TeacherMaterial;
use App\Models\User;
use App\Services\LessonActivityService;
use App\Services\TeacherLessonService;
use App\Services\TeacherScheduleService;
use App\Services\TeacherStudentsService;
use App\Support\HumanDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Занятие учителя: предстоящее (обзор, материалы, история), идущее и отчёт о проведённом.
 * Макеты: Lesson, LsLive, LsEnded, LsReschedule, LsCancel, LsDeleteRequest, LsStartBlocked (docs/design/BRAND.md).
 * Окна «Ученики и название» и «Презентации к занятию» заменяют форму занятия старого кабинета (RoomResource).
 */
#[Layout('components.layouts.cabinet', ['title' => 'Занятие', 'active' => 'schedule'])]
class Lesson extends Component
{
    use LessonRows, MarksPayments, PlansLessons, StartsLessons, TeacherScreen, WithFileUploads;

    public int $roomId;

    /**
     * Какое занятие серии показано: исходное время по расписанию (Y-m-d\TH:i). Пусто — ближайшее.
     * Нужно, чтобы после отмены или переноса экран показывал именно это занятие («отменено · причина», «перенесено с …»).
     */
    #[Url(except: '')]
    public string $at = '';

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    /** Проведённое занятие (MeetingSession), отчёт о котором показан. */
    #[Url(except: null)]
    public ?int $session = null;

    public bool $confirmStop = false;

    /* Отмена (LsCancel): одно занятие или серия, причина, уведомление ученикам по галочке */
    public bool $cancelOpen = false;

    /** one — только это занятие, series — это и все следующие занятия правила. */
    public string $cancelScope = 'one';

    public ?int $cancelScheduleId = null;

    /** Исходное время отменяемого занятия (Y-m-d\TH:i). */
    public string $cancelAt = '';

    public string $cancelReason = '';

    public bool $cancelNotify = true;

    /** Убрать занятие в архив, если занятий в расписании больше не останется (только по явному выбору). */
    public bool $cancelArchive = false;

    /* Перенос (LsReschedule): одно занятие или это и все следующие; без занятия — «Назначить время» */
    public bool $rescheduleOpen = false;

    public ?int $rsScheduleId = null;

    /** Исходное время переносимого занятия (Y-m-d\TH:i). */
    public string $rsAt = '';

    /** one — только это занятие, following — это и все следующие. */
    public string $rsScope = 'one';

    /** Повтор нового времени, когда занятия ещё нет («Назначить время»): once | weekly. */
    public string $rsRepeat = 'weekly';

    public string $rsDate = '';

    public string $rsTime = '';

    public int $rsDuration = RoomSchedule::DEFAULT_DURATION;

    /** @var array<int> */
    public array $rsDays = [];

    public string $rsUntil = '';

    public bool $rsNotify = true;

    /* План занятия (карточка «План занятия» + «Изменить») */
    public bool $lessonPlanOpen = false;

    public string $lessonPlanBody = '';

    /* Запрос удаления проведённого занятия (LsDeleteRequest) */
    public bool $deleteRequestOpen = false;

    public string $deletionReason = '';

    /* Ученики и название (EditRoom старого кабинета) */
    public bool $editOpen = false;

    public string $editName = '';

    /** individual — один ученик, group — несколько. Тип в базе всё равно считается по числу учеников. */
    public string $editKind = 'individual';

    /** @var array<int> */
    public array $editStudents = [];

    public string $editSearch = '';

    /* Презентации: открываются в классе при старте */
    public bool $presentationsOpen = false;

    /** @var array<int, TemporaryUploadedFile> */
    public array $presentationUploads = [];

    /** @var array<int, string> исходные имена выбранных файлов (как в «Материалах») */
    public array $presentationUploadNames = [];

    /** Ключ презентации, удаление которой подтверждают. */
    public ?string $confirmPresentation = null;

    /** Обновляем, когда занятие начинается или завершается. */
    #[On('echo:rooms,.room.status.updated')]
    public function refreshRooms(): void {}

    public function mount(int|string $room): void
    {
        $teacher = $this->authorizeTeacher();

        $model = Room::withTrashed()->find((int) $room);
        abort_unless($model && (int) $model->user_id === $teacher->id, 404);
        $this->roomId = $model->id;

        if (! in_array($this->tab, ['overview', 'materials', 'history'], true)) {
            $this->tab = 'overview';
        }

        if ($this->session && ! $this->sessionModel()) {
            $this->session = null;
        }
    }

    /** Занятие учителя (в том числе архивное). Владение проверяется на каждом запросе. */
    private function room(): Room
    {
        $room = Room::withTrashed()->with(['participants:id,name,first_name,username,avatar', 'schedules'])->find($this->roomId);
        abort_unless($room && (int) $room->user_id === auth()->id(), 404);

        return $room;
    }

    private function sessionModel(): ?MeetingSession
    {
        return $this->session
            ? MeetingSession::where('room_id', $this->roomId)->find($this->session)
            : null;
    }

    public function updatedTab(): void
    {
        if (! in_array($this->tab, ['overview', 'materials', 'history'], true)) {
            $this->tab = 'overview';
        }
    }

    /*
     |--------------------------------------------------------------------------
     | Завершить занятие
     |--------------------------------------------------------------------------
     | Само завершение — rooms.stop (RoomController::stop), он возвращает назад. Перед переходом
     | запоминаем в адресе идущее занятие, чтобы после завершения открылся его отчёт.
     */

    public function askStop(): void
    {
        $running = MeetingSession::where('room_id', $this->roomId)->where('status', 'running')->latest('started_at')->first();
        $this->session = $running?->id;
        $this->confirmStop = true;
    }

    public function keepRunning(): void
    {
        $this->confirmStop = false;
        $this->session = null;
    }

    /*
     |--------------------------------------------------------------------------
     | Какое занятие показано
     |--------------------------------------------------------------------------
     */

    private static function atKey(Carbon $at): string
    {
        return $at->format('Y-m-d\TH:i');
    }

    private static function parseAt(string $at): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $at)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d\TH:i', $at)->startOfMinute();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Показанное занятие: выбранное в адресе (в том числе отменённое) или ближайшее.
     *
     * @return array{start:Carbon, end:Carbon, schedule:?RoomSchedule, original:Carbon, exception:?\App\Models\RoomScheduleException}|null
     */
    private function occurrence(Room $room): ?array
    {
        if ($room->trashed()) {
            return null;
        }

        $service = app(TeacherScheduleService::class);
        $at = self::parseAt($this->at);

        return ($at ? $service->occurrenceAt($room, $at) : null) ?? $service->nextOccurrence($room);
    }

    /** Занятие по правилу и исходному времени из окна (правило — этого занятия). */
    private function occurrenceFor(Room $room, ?int $scheduleId, string $at): ?array
    {
        $original = self::parseAt($at);
        $occurrence = $original ? app(TeacherScheduleService::class)->occurrenceAt($room, $original) : null;

        return $occurrence && $occurrence['schedule']->id === $scheduleId ? $occurrence : null;
    }

    /*
     |--------------------------------------------------------------------------
     | Перенести (LsReschedule): только это занятие — исключение, это и все следующие — новое правило
     |--------------------------------------------------------------------------
     */

    public function openReschedule(bool $next = false): void
    {
        $room = $this->room();
        abort_if($room->trashed(), 403);

        $occurrence = $next ? app(TeacherScheduleService::class)->nextOccurrence($room) : $this->occurrence($room);
        if ($occurrence && $occurrence['exception']?->isCancelled()) {
            $occurrence = null;
        }
        $default = now()->addHour()->startOfHour();

        $this->resetValidation();
        $this->rsScheduleId = $occurrence['schedule']?->id;
        $this->rsAt = $occurrence ? self::atKey($occurrence['original']) : '';
        $this->rsScope = 'one';
        $this->rsNotify = true;
        $this->rsUntil = '';

        $start = $occurrence['start'] ?? $default;
        $schedule = $occurrence['schedule'] ?? null;
        $this->rsRepeat = 'weekly';
        $this->rsDate = $start->format('Y-m-d');
        $this->rsTime = $start->format('H:i');
        $this->rsDays = match ($schedule?->recurrence_type) {
            'daily' => [0, 1, 2, 3, 4, 5, 6],
            'weekly' => array_map('intval', $schedule->recurrence_days ?? []),
            default => [$start->dayOfWeek],
        };
        $this->rsDuration = $occurrence ? (int) $occurrence['start']->diffInMinutes($occurrence['end']) : (int) (auth()->user()->lessonTypes()
            ->where('type', $room->type === 'group' ? 'group' : 'individual')->value('duration') ?: RoomSchedule::DEFAULT_DURATION);
        $this->rescheduleOpen = true;
    }

    public function toggleRsDay(int $day): void
    {
        if ($day >= 0 && $day <= 6) {
            $this->rsDays = in_array($day, $this->rsDays, true) ? array_values(array_diff($this->rsDays, [$day])) : [...$this->rsDays, $day];
        }
    }

    public function updatedRsScope(): void
    {
        if (! in_array($this->rsScope, ['one', 'following'], true)) {
            $this->rsScope = 'one';
        }
    }

    public function updatedRsRepeat(): void
    {
        if (! in_array($this->rsRepeat, ['once', 'weekly'], true)) {
            $this->rsRepeat = 'weekly';
        }
    }

    public function closeReschedule(): void
    {
        $this->rescheduleOpen = false;
    }

    public function saveReschedule(): void
    {
        $room = $this->room();
        abort_if($room->trashed(), 403);
        $teacher = auth()->user();
        $service = app(TeacherLessonService::class);

        $occurrence = $this->rsScheduleId ? $this->occurrenceFor($room, $this->rsScheduleId, $this->rsAt) : null;
        abort_if($this->rsScheduleId && ! $occurrence, 404);

        $mode = match (true) {
            ! $occurrence => 'new',
            $this->rsScope === 'following' && $occurrence['schedule']->type !== 'once' => 'following',
            default => 'one',
        };
        $weekly = $mode === 'following' || ($mode === 'new' && $this->rsRepeat === 'weekly');

        $this->validate([
            'rsDate' => ['required', 'date_format:Y-m-d'],
            'rsTime' => ['required', 'date_format:H:i'],
            'rsDuration' => ['required', 'integer', 'min:1', 'max:1440'],
            'rsDays' => [Rule::requiredIf($weekly), 'array'],
            'rsDays.*' => ['integer', 'between:0,6'],
            'rsUntil' => ['nullable', 'date', 'after_or_equal:rsDate'],
        ], [
            'rsDate.required' => 'Укажите дату',
            'rsTime.required' => 'Укажите время',
            'rsTime.date_format' => 'Время в формате 17:00',
            'rsDays.required' => 'Выберите дни недели',
            'rsUntil.after_or_equal' => 'Дата окончания раньше новой даты',
        ]);

        $start = Carbon::parse($this->rsDate . ' ' . $this->rsTime);
        if ($mode === 'one' && $start->copy()->addMinutes($this->rsDuration)->isPast()) {
            $this->addError('rsDate', 'Это время уже прошло');

            return;
        }

        $who = $this->notifyWho($room);
        $sent = $room->participants->isNotEmpty() ? ($this->rsNotify ? ', ' . $who . ' ' . ($room->participants->count() > 1 ? 'получат' : 'получит') . ' уведомление' : '') : '';

        if ($mode === 'new') {
            $attributes = TeacherLessonService::scheduleAttributes($this->rsRepeat, $this->rsDate, $this->rsTime, $this->rsDuration, $this->rsDays, $this->rsUntil ?: null);
            $service->addSchedule($room, $attributes, $teacher, $this->rsNotify);
            $first = TeacherLessonService::firstOccurrence($this->rsRepeat, $this->rsDate, $this->rsTime, $this->rsDays);
            $this->at = '';
            $message = ($weekly
                ? 'Расписание: ' . TeacherScheduleService::repeatLabel(new RoomSchedule($attributes)) . ' в ' . $this->rsTime
                : 'Занятие назначено на ' . ($first ? HumanDate::at($first) : $this->rsTime)) . $sent;
        } elseif ($mode === 'following') {
            $schedule = $service->rescheduleFollowing($occurrence['schedule'], $occurrence['original'], $this->rsDate, $this->rsTime, $this->rsDuration, $this->rsDays, $this->rsNotify, $teacher);
            $this->at = '';
            $message = 'Расписание изменено: ' . TeacherScheduleService::repeatLabel($schedule) . ' в ' . $this->rsTime . $sent;
        } else {
            $service->moveOccurrence($occurrence['schedule'], $occurrence['original'], $start, $this->rsDuration, $this->rsNotify, $teacher);
            $this->at = self::atKey($occurrence['original']);
            $message = 'Занятие перенесено на ' . HumanDate::at($start) . $sent;
        }

        $this->rescheduleOpen = false;
        $this->dispatch('toast', message: $message);
    }

    /** «Алина» — одному ученику, «ученики» — группе. */
    private function notifyWho(Room $room): string
    {
        $student = $room->participants->count() === 1 ? $room->participants->first() : null;

        return $student ? ($student->first_name ?: $student->name) : 'ученики';
    }

    /*
     |--------------------------------------------------------------------------
     | Отменить (LsCancel): только это занятие — исключение, всю серию — правило заканчивается на этом занятии.
     | Занятие уходит в архив только по явному выбору и только если занятий в расписании не осталось.
     |--------------------------------------------------------------------------
     */

    public function openCancel(): void
    {
        $room = $this->room();
        abort_if($room->trashed(), 403);

        $occurrence = $this->occurrence($room);
        abort_unless($occurrence && ! $occurrence['exception']?->isCancelled(), 404);

        $this->resetValidation();
        $this->cancelScheduleId = $occurrence['schedule']->id;
        $this->cancelAt = self::atKey($occurrence['original']);
        $this->cancelScope = 'one';
        $this->cancelReason = '';
        $this->cancelNotify = true;
        $this->cancelArchive = false;
        $this->cancelOpen = true;
    }

    public function updatedCancelScope(): void
    {
        if (! in_array($this->cancelScope, ['one', 'series'], true)) {
            $this->cancelScope = 'one';
        }
    }

    public function closeCancel(): void
    {
        $this->cancelOpen = false;
    }

    public function confirmCancel()
    {
        $room = $this->room();
        abort_if($room->trashed(), 403);
        $teacher = auth()->user();
        $service = app(TeacherLessonService::class);

        $this->validate(['cancelReason' => ['nullable', 'string', 'max:500']], ['cancelReason.max' => 'Причина — не длиннее 500 символов']);

        $occurrence = $this->occurrenceFor($room, $this->cancelScheduleId, $this->cancelAt);
        abort_unless($occurrence, 404);
        $schedule = $occurrence['schedule'];
        $series = $this->cancelScope === 'series' && $schedule->type !== 'once';

        $series
            ? $service->cancelSeries($schedule, $occurrence['original'], $this->cancelReason, $this->cancelNotify, $teacher)
            : $service->cancelOccurrence($schedule, $occurrence['original'], $this->cancelReason, $this->cancelNotify, $teacher);

        $this->cancelOpen = false;
        $who = $this->notifyWho($room);
        $many = $room->participants->count() > 1;
        $sent = $room->participants->isEmpty() ? '' : ($this->cancelNotify
            ? ', ' . $who . ' ' . ($many ? 'получат' : 'получит') . ' уведомление'
            : ', ' . ($many ? 'ученикам' : 'ученику') . ' ничего не отправляли');
        $message = ($series ? 'Серия занятий отменена' : 'Занятие отменено') . $sent;

        if ($this->cancelArchive && ! $this->hasOtherLessons($room->fresh())) {
            $service->archive($room, $teacher, notify: false);
            session()->flash('toast', $message . '. Занятие в архиве');

            return $this->redirect(Route::has('cabinet.teacher.schedule') ? route('cabinet.teacher.schedule') : url('/tutor/schedule-calendar'));
        }

        $this->at = self::atKey($occurrence['original']);
        $this->dispatch('toast', message: $message);

        return null;
    }

    /** Остались ли у занятия будущие занятия в расписании. */
    private function hasOtherLessons(Room $room, ?int $exceptScheduleId = null): bool
    {
        return $room->schedules()->where('is_active', true)->with('exceptions')->get()
            ->reject(fn (RoomSchedule $s) => $s->id === $exceptScheduleId)
            ->contains(fn (RoomSchedule $s) => $s->nextOccurrenceDetails() !== null);
    }

    /*
     |--------------------------------------------------------------------------
     | План занятия: у конкретного занятия (по исходному времени), виден и во время занятия
     |--------------------------------------------------------------------------
     */

    public function openLessonPlan(): void
    {
        $room = $this->room();
        abort_if($room->trashed(), 403);
        $occurrence = $this->occurrence($room);
        abort_unless($occurrence, 404);

        $this->resetValidation();
        $this->lessonPlanBody = (string) app(TeacherLessonService::class)->lessonPlan($room, $occurrence['original'])?->body;
        $this->lessonPlanOpen = true;
    }

    public function closeLessonPlan(): void
    {
        $this->lessonPlanOpen = false;
    }

    public function saveLessonPlan(): void
    {
        $room = $this->room();
        abort_if($room->trashed(), 403);
        $occurrence = $this->occurrence($room);
        abort_unless($occurrence, 404);

        $this->validate(['lessonPlanBody' => ['nullable', 'string', 'max:5000']], ['lessonPlanBody.max' => 'План — не длиннее 5000 символов']);

        $plan = app(TeacherLessonService::class)->saveLessonPlan($room, $occurrence['original'], $this->lessonPlanBody, auth()->user());
        $this->lessonPlanOpen = false;
        $this->dispatch('toast', message: $plan ? 'План занятия сохранён' : 'План занятия очищен');
    }

    /*
     |--------------------------------------------------------------------------
     | Ученики и название: состав, тип и название занятия (EditRoom старого кабинета)
     |--------------------------------------------------------------------------
     */

    public function openEdit(): void
    {
        $room = $this->room();
        abort_if($room->trashed(), 403);

        $this->resetValidation();
        $this->editName = $room->name;
        $this->editStudents = $room->participants->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->editKind = count($this->editStudents) > 1 || $room->type === 'group' ? 'group' : 'individual';
        $this->editSearch = '';
        $this->editOpen = true;
    }

    public function closeEdit(): void
    {
        $this->editOpen = false;
    }

    public function updatedEditKind(): void
    {
        if (! in_array($this->editKind, ['individual', 'group'], true)) {
            $this->editKind = 'individual';
        }

        // В индивидуальном занятии остаётся один ученик — первый выбранный
        if ($this->editKind === 'individual' && count($this->editStudents) > 1) {
            $this->editStudents = [(int) $this->editStudents[0]];
        }
    }

    /** Выбор ученика: в индивидуальном — вместо текущего, в групповом — добавить или убрать. */
    public function pickEditStudent(int $id): void
    {
        $chosen = in_array($id, $this->editStudents, true);

        $this->editStudents = match (true) {
            $this->editKind === 'individual' => $chosen ? [] : [$id],
            $chosen => array_values(array_diff($this->editStudents, [$id])),
            default => [...$this->editStudents, $id],
        };
    }

    public function saveEdit(): void
    {
        $room = $this->room();
        abort_if($room->trashed(), 403);
        $teacher = auth()->user();
        $service = app(TeacherLessonService::class);
        $allowed = $service->participantOptions($teacher, $room)->pluck('id')->all();

        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editKind' => ['required', Rule::in(['individual', 'group'])],
            'editStudents' => ['required', 'array', $this->editKind === 'individual' ? 'max:1' : 'min:1'],
            'editStudents.*' => ['integer', Rule::in($allowed)],
        ], [
            'editName.required' => 'Назовите занятие: предмет или тему',
            'editStudents.required' => $this->editKind === 'individual' ? 'Выберите ученика' : 'Добавьте учеников в группу',
            'editStudents.max' => 'В индивидуальном занятии — один ученик',
            'editStudents.*.in' => 'Этого ученика нет среди ваших — обновите страницу',
        ]);

        ['added' => $added, 'removed' => $removed] = $service->updateLesson($room, $teacher, $this->editName, $this->editStudents);

        $this->editOpen = false;
        $this->dispatch('toast', message: self::editToast($added, $removed));
    }

    /** «Алина в занятии, получит уведомление» / «Ученики добавлены, получат уведомление» / «Сохранено». */
    private static function editToast(Collection $added, Collection $removed): string
    {
        $first = fn (Collection $people) => $people->first()->first_name ?: $people->first()->name;

        return match (true) {
            $added->count() === 1 => $first($added) . ' в занятии, получит уведомление',
            $added->count() > 1 => 'Ученики добавлены, получат уведомление',
            $removed->count() === 1 => $first($removed) . ' больше не в этом занятии',
            $removed->count() > 1 => 'Ученики убраны из занятия',
            default => 'Сохранено',
        };
    }

    /*
     |--------------------------------------------------------------------------
     | Презентации к занятию (rooms.presentations): открываются в классе при старте
     |--------------------------------------------------------------------------
     */

    public function openPresentations(): void
    {
        abort_if($this->room()->trashed(), 403);

        $this->resetValidation();
        $this->discardPresentationUploads();
        $this->presentationsOpen = true;
    }

    public function closePresentations(): void
    {
        $this->discardPresentationUploads();
        $this->presentationsOpen = false;
    }

    /** Файлы доехали до сервера: проверяем формат и размер (как в RoomResource). */
    public function updatedPresentationUploads(): void
    {
        if (! $this->presentationsOpen) {
            $this->discardPresentationUploads();

            return;
        }

        try {
            $this->validate(
                ['presentationUploads.*' => self::presentationRules()],
                self::presentationMessages(),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->discardPresentationUploads();
            $this->addError('presentationUploads', collect($e->errors())->flatten()->unique()->implode(' '));
        }
    }

    public function presentationUploadFailed(): void
    {
        $this->addError('presentationUploads', 'Не удалось передать файлы. Возможно, файл слишком большой или прервалась связь — попробуйте ещё раз.');
    }

    public function savePresentations(): void
    {
        $room = $this->room();
        abort_if($room->trashed(), 403);

        if (empty($this->presentationUploads)) {
            $this->addError('presentationUploads', 'Выберите файлы или дождитесь, пока они передадутся.');

            return;
        }

        $this->validate(['presentationUploads.*' => self::presentationRules()], self::presentationMessages());

        $service = app(TeacherLessonService::class);
        $saved = 0;
        foreach ($this->presentationUploads as $i => $file) {
            $saved += $service->addPresentation($room, $file, $this->presentationUploadNames[$i] ?? null) ? 1 : 0;
        }

        $this->presentationUploads = [];
        $this->presentationUploadNames = [];
        $this->presentationsOpen = false;
        $this->tab = 'materials';

        $this->dispatch('toast', message: $saved > 0
            ? ($saved === 1 ? 'Презентация добавлена, откроется в классе при старте' : 'Добавлено ' . plural_ru($saved, 'презентация', 'презентации', 'презентаций') . ', откроются в классе при старте')
            : 'Не удалось сохранить файлы — попробуйте ещё раз', tone: $saved > 0 ? 'ok' : 'danger');
    }

    public function askDeletePresentation(string $key): void
    {
        abort_unless($this->presentationPath($this->room(), $key), 404);
        $this->confirmPresentation = $key;
    }

    public function deletePresentation(): void
    {
        $room = $this->room();
        abort_if($room->trashed(), 403);
        $path = $this->confirmPresentation ? $this->presentationPath($room, $this->confirmPresentation) : null;
        abort_unless($path, 404);

        app(TeacherLessonService::class)->removePresentation($room, $path);
        $this->confirmPresentation = null;
        $this->dispatch('toast', message: 'Презентация удалена');
    }

    private function presentationPath(Room $room, string $key): ?string
    {
        return collect($room->presentations ?? [])->first(fn ($path) => is_string($path) && self::presentationKey($path) === $key);
    }

    private static function presentationKey(string $path): string
    {
        return md5($path);
    }

    private static function presentationRules(): array
    {
        return ['file', 'mimetypes:' . implode(',', TeacherLessonService::PRESENTATION_MIMES), 'max:' . TeacherLessonService::PRESENTATION_MAX_KB];
    }

    private static function presentationMessages(): array
    {
        return [
            'presentationUploads.*.mimetypes' => 'Подойдут PDF, PowerPoint, Word, Excel или фото JPG и PNG.',
            'presentationUploads.*.max' => 'Файл больше 200 МБ загрузить нельзя.',
        ];
    }

    private function discardPresentationUploads(): void
    {
        foreach ($this->presentationUploads as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                try {
                    $file->delete();
                } catch (\Throwable) {
                    // временный файл мог уже исчезнуть
                }
            }
        }

        $this->presentationUploads = [];
        $this->presentationUploadNames = [];
    }

    /*
     |--------------------------------------------------------------------------
     | Запросить удаление проведённого занятия (RequestSessionDeletionAction)
     |--------------------------------------------------------------------------
     */

    public function openDeleteRequest(): void
    {
        $this->resetValidation();
        $this->deletionReason = '';
        $this->deleteRequestOpen = true;
    }

    public function closeDeleteRequest(): void
    {
        $this->deleteRequestOpen = false;
    }

    public function sendDeleteRequest(): void
    {
        $this->validate(['deletionReason' => ['required', 'string', 'min:3', 'max:2000']], [
            'deletionReason.required' => 'Укажите причину',
            'deletionReason.min' => 'Опишите причину чуть подробнее',
        ]);

        $session = $this->sessionModel();
        abort_unless($session && $session->status === 'completed', 404);

        if (! $session->deletion_requested_at) {
            $session->requestDeletion(trim($this->deletionReason), auth()->user());
        }

        $this->deleteRequestOpen = false;
        $this->dispatch('toast', message: 'Запрос отправлен, решение придёт в уведомлениях');
    }

    public function revokeDeleteRequest(): void
    {
        $session = $this->sessionModel();
        abort_unless($session, 404);

        if ($session->deletion_requested_at) {
            $session->cancelDeletionRequest();
            $this->dispatch('toast', message: 'Запрос отозван, занятие остаётся в истории');
        }
    }

    /*
     |--------------------------------------------------------------------------
     | Экран
     |--------------------------------------------------------------------------
     */

    public function render()
    {
        $teacher = auth()->user();
        $room = $this->room();
        $session = $this->sessionModel();
        $service = app(TeacherScheduleService::class);

        $mode = match (true) {
            $session && $session->status === 'completed' && ! $this->confirmStop => 'report',
            (bool) $room->is_running => 'live',
            default => 'upcoming',
        };

        $next = $this->occurrence($room);
        $cancelled = (bool) $next && ($next['exception']?->isCancelled() ?? false);
        $running = $mode === 'live'
            ? MeetingSession::where('room_id', $room->id)->where('status', 'running')->latest('started_at')->first()
            : null;
        $startBlock = $this->startBlock($teacher);
        $participants = $room->participants;
        $group = $participants->count() > 1 || $room->type === 'group';
        $plan = $next && $mode !== 'report' ? app(TeacherLessonService::class)->lessonPlan($room, $next['original']) : null;

        $data = [
            'room' => $room,
            'mode' => $mode,
            'group' => $group,
            'archived' => $room->trashed(),
            'next' => $next,
            'cancelled' => $cancelled,
            'planItems' => $plan?->items() ?? [],
            'hasOccurrence' => (bool) $next,
            'startBlock' => $startBlock,
            'backUrl' => Route::has('cabinet.teacher.schedule') ? route('cabinet.teacher.schedule') : url('/tutor/schedule-calendar'),
            'chatUrl' => \App\Services\MessengerService::url(auth()->user(), $room->id),
            'taskUrl' => Route::has('cabinet.teacher.task-new') ? route('cabinet.teacher.task-new', ['room' => $room->id]) : url('/tutor/homework/create'),
            'recordingsUrl' => Route::has('cabinet.teacher.recordings') ? route('cabinet.teacher.recordings') : url('/tutor/recordings'),
            'whoLine' => $room->name . ($group ? '' : ($participants->first() ? ' · ' . $participants->first()->name : '')),
            'otherRunning' => $this->otherRunningRoomId($teacher, $room->id) !== null,
            'homework' => $this->homework($room),
        ];

        $data += match ($mode) {
            'report' => $this->report($teacher, $room, $session, $service->nextOccurrence($room)),
            'live' => $this->live($teacher, $room, $running, $next),
            default => $this->upcoming($teacher, $room, $next),
        };

        if ($mode !== 'report') {
            $data += $this->people($teacher, $room);
            $data += match ($this->tab) {
                'materials' => $this->materials($room),
                'history' => $this->history($teacher, $room),
                default => [],
            };
        }

        return view('livewire.cabinet.teacher.lesson', $data + $this->modals($room, $next, $session) + $this->markPaidView($teacher) + $this->planView($teacher))
            ->title($room->name);
    }

    /** «Сегодня, 16:00–17:00», «Чт, 3 октября, 16:00–17:00». */
    private static function when(Carbon $start, Carbon $end): string
    {
        return Str::ucfirst(HumanDate::day($start)) . ', ' . $start->format('H:i') . '–' . $end->format('H:i');
    }

    /** Строка фактов под заголовком: части через «·», срочное — жирным. */
    private static function facts(array $parts): HtmlString
    {
        return new HtmlString(collect($parts)->filter()->map(fn ($p) => is_array($p)
            ? '<span class="font-semibold text-ink">' . e($p[0]) . '</span>'
            : e($p))->implode(' · '));
    }

    private function upcoming(User $teacher, Room $room, ?array $next): array
    {
        $exception = $next['exception'] ?? null;
        $schedule = $next['schedule'] ?? null;

        $parts = match (true) {
            $room->trashed() => [['Занятие в архиве']],
            ! $next => [['Время не назначено']],
            (bool) $exception?->isCancelled() => $this->cancelledFacts($room, $next),
            (bool) $exception?->isMoved() => [
                self::when($next['start'], $next['end']),
                ['перенесено ' . TeacherScheduleService::movedFromLabel($next['original'])],
                $schedule?->type !== 'once' ? 'остальные — ' . TeacherScheduleService::repeatLabel($schedule) : null,
            ],
            $next['end']->isPast() => [self::when($next['start'], $next['end']), ['уже прошло'], TeacherScheduleService::repeatLabel($schedule)],
            default => [
                self::when($next['start'], $next['end']),
                $next['start']->isFuture()
                    ? ($next['start']->isToday() ? ['начнётся ' . HumanDate::until($next['start'])] : 'начнётся ' . HumanDate::until($next['start']))
                    : ['идёт по расписанию'],
                TeacherScheduleService::repeatLabel($schedule),
            ],
        };

        return ['sub' => self::facts($parts)];
    }

    /** «Сегодня, 16:00–17:00 · отменено · Алина заболела · следующее — чт, 3 октября» (или «серия отменена · больше не повторяется»). */
    private function cancelledFacts(Room $room, array $occurrence): array
    {
        $schedule = $occurrence['schedule'];
        $reason = $occurrence['exception']->reason;
        $seriesEnded = $schedule->type !== 'once' && $schedule->end_date && $schedule->end_date->isSameDay($occurrence['original']);
        $following = app(TeacherScheduleService::class)->nextOccurrence($room);
        $following = $following && $following['start']->gt($occurrence['original']) ? $following : null;

        return [
            self::when($occurrence['original'], $occurrence['original']->copy()->addMinutes($schedule->minutes())),
            [($seriesEnded ? 'серия отменена' : 'отменено') . ($reason ? ' · ' . $reason : '')],
            match (true) {
                (bool) $following => 'следующее — ' . HumanDate::day($following['start']),
                $schedule->type !== 'once' => 'больше не повторяется',
                default => null,
            },
        ];
    }

    private function live(User $teacher, Room $room, ?MeetingSession $running, ?array $next): array
    {
        $startedAt = $running?->started_at;
        $minutes = $startedAt ? max(1, (int) $startedAt->diffInMinutes(now())) : null;
        $snapshot = $running?->settings_snapshot ?? [];

        // Кто сейчас в классе: участники из вебхуков BBB, которые ещё не вышли
        $present = collect($running?->analytics_data['participants'] ?? [])
            ->filter(function ($p) {
                $joined = $p['last_joined_at'] ?? $p['joined_at'] ?? null;
                $left = $p['left_at'] ?? null;

                return ! $left || ($joined && Carbon::parse($joined)->gt(Carbon::parse($left)));
            })
            ->map(function ($p) use ($teacher, $startedAt) {
                $id = is_numeric($p['user_id'] ?? null) ? (int) $p['user_id'] : null;
                $at = isset($p['joined_at']) ? Carbon::parse($p['joined_at'])->timezone(config('app.timezone'))->format('H:i') : null;

                return $id === $teacher->id
                    ? ['me' => true, 'id' => $id, 'name' => 'Вы', 'avatar' => $teacher->name, 'sub' => 'Ведёте занятие' . ($startedAt ? ' с ' . $startedAt->format('H:i') : '')]
                    : ['me' => false, 'id' => $id ?? 0, 'name' => $p['full_name'] ?? 'Гость', 'avatar' => $p['full_name'] ?? 'Гость', 'sub' => $at ? 'Подключился в ' . $at : 'В классе'];
            })
            ->sortBy(fn ($p) => $p['me'] ? 1 : 0)
            ->values();

        return [
            'sub' => self::facts([
                [$minutes ? 'Идёт ' . plural_ru($minutes, 'минуту', 'минуты', 'минут') : 'Идёт сейчас'],
                ! empty($snapshot['record']) && ! empty($snapshot['autoStartRecording']) ? 'Идёт запись' : null,
                $next ? self::when($next['start'], $next['end']) : null,
                TeacherScheduleService::repeatLabel($next['schedule'] ?? null),
            ]),
            'present' => $present,
            'guestUrl' => route('rooms.join', $room),
            'joinUrl' => route('rooms.connect', $room),
            'stopUrl' => route('rooms.stop', $room),
        ];
    }

    /** Ученики занятия, цена и неоплаченное (правая колонка обзора). */
    private function people(User $teacher, Room $room): array
    {
        $students = app(TeacherStudentsService::class);
        $lessonType = $teacher->lessonTypes()->where('type', $room->type === 'group' ? 'group' : 'individual')->first();
        $unpaid = PaymentRecord::unpaid()->where('teacher_id', $teacher->id)
            ->whereIn('student_id', $room->participants->pluck('id'))
            ->get()
            ->groupBy('student_id');

        $people = $room->participants->map(function (User $u) use ($teacher, $students, $room, $unpaid) {
            $since = $students->since($teacher, $u->id);
            $own = $unpaid->get($u->id, collect());

            return [
                'id' => $u->id,
                'name' => $u->name,
                'since' => $since ? 'Занимается с ' . HumanDate::month($since, true) : null,
                'url' => TeacherStudentsService::studentUrl($u),
                'price' => $room->getEffectivePrice($u->id),
                'unpaid' => $own->count(),
                'overdue' => $own->contains(fn (PaymentRecord $r) => $r->isOverdue()),
                'justPaid' => isset($this->justPaid[$u->id]),
            ];
        });

        return [
            'people' => $people,
            'priceUnit' => $lessonType?->isMonthly() ? 'в месяц' : 'за занятие',
            'monthly' => (bool) $lessonType?->isMonthly(),
        ];
    }

    /** Задания, выданные к занятию, со статусом сдачи. */
    private function homework(Room $room): Collection
    {
        $group = $room->participants->count() > 1;

        return Homework::where('room_id', $room->id)
            ->with('submissions')
            ->withCount('students')
            ->latest()
            ->limit(3)
            ->get()
            ->map(function (Homework $h) use ($group) {
                $submitted = $h->submissions->whereNotNull('submitted_at');
                $sub = $h->submissions->first();

                [$tone, $label] = match (true) {
                    $group || $h->students_count > 1 => [null, 'Сдали ' . $submitted->count() . ' из ' . $h->students_count],
                    $sub?->status === HomeworkSubmission::STATUS_REVISION_REQUESTED => ['danger', 'На доработке'],
                    $sub?->grade !== null && $sub !== null => ['ok', 'Оценка ' . $sub->grade],
                    (bool) $sub?->submitted_at => ['neutral', 'На проверке'],
                    $h->is_overdue => ['danger', 'Срок прошёл'],
                    default => ['neutral', 'Ещё не сдано'],
                };

                return [
                    'id' => $h->id,
                    'title' => $h->title,
                    'deadline' => $h->deadline && $h->deadline->isFuture() ? 'сдать ' . HumanDate::at($h->deadline) : null,
                    'tone' => $tone,
                    'label' => $label,
                    'url' => Route::has('cabinet.teacher.task') ? route('cabinet.teacher.task', $h) : url('/tutor/homework/' . $h->id),
                ];
            });
    }

    /** Материалы к занятию: презентации класса и материалы, открытые этому занятию. */
    private function materials(Room $room): array
    {
        $files = collect($room->presentations ?? [])->filter(fn ($path) => is_string($path) && $path !== '')->map(fn (string $path) => [
            'key' => 'p-' . self::presentationKey($path),
            'presentation' => self::presentationKey($path),
            'name' => TeacherLessonService::presentationName($room, $path),
            'file' => TeacherLessonService::presentationName($room, $path),
            'sub' => 'Откроется в классе при старте',
            'url' => Storage::disk('s3')->url($path),
        ])->values();

        $shared = TeacherMaterial::where('teacher_id', $room->user_id)
            ->where('visibility', TeacherMaterial::VISIBILITY_ROOMS)
            ->whereHas('rooms', fn ($q) => $q->where('rooms.id', $room->id))
            ->orderBy('title')
            ->get()
            ->map(fn (TeacherMaterial $m) => [
                'key' => 'm-' . $m->id,
                'name' => $m->title ?: $m->original_name,
                'file' => $m->original_name ?: $m->file_path,
                'thumb' => $m->preview_url,
                'sub' => 'Видно ученикам занятия',
                'url' => $m->file_url,
                'presentation' => null,
            ]);

        return [
            'files' => $files->concat($shared)->values(),
            'materialsUrl' => Route::has('cabinet.teacher.materials') ? route('cabinet.teacher.materials') : url('/tutor/materials'),
        ];
    }

    /** Прошедшие занятия: длительность, посещаемость, долг, запись; отменённые — с причиной. */
    private function history(User $teacher, Room $room): array
    {
        $service = app(TeacherScheduleService::class);
        $sessions = $service->sessions($teacher->id, Carbon::create(2000), null, $room->id)->take(30);
        $overdue = TeacherScheduleService::overdueSessionIds($teacher->id, $sessions->pluck('id'));
        $recordings = $this->readyRecordings($sessions);

        $held = $sessions->map(function (MeetingSession $s) use ($overdue, $recordings) {
            $att = TeacherScheduleService::attendance($s);

            return [
                'id' => 's' . $s->id,
                'at' => $s->started_at,
                'title' => Str::ucfirst(HumanDate::day($s->started_at)) . ' в ' . $s->started_at->format('H:i'),
                'sub' => collect([
                    plural_ru(TeacherScheduleService::sessionMinutes($s), 'минута', 'минуты', 'минут'),
                    $att['total'] > 1 ? 'были ' . $att['attended'] . ' из ' . $att['total'] : ($att['total'] === 1 && ! $att['attended'] ? 'ученик не пришёл' : null),
                ])->filter()->implode(' · '),
                'unpaid' => in_array($s->id, $overdue, true),
                'deletion' => (bool) $s->deletion_requested_at,
                'url' => $this->lessonUrl($s->room_id, $s->id),
                'recordingUrl' => isset($recordings[$s->id]) ? $this->recordingUrl($recordings[$s->id]) : null,
            ];
        });

        $cancelled = $service->cancelled($teacher->id, Carbon::create(2000), now(), $room->id)->take(30)
            ->map(fn (\App\Models\RoomScheduleException $e) => [
                'id' => 'c' . $e->id,
                'at' => $e->original_starts_at,
                'title' => Str::ucfirst(HumanDate::day($e->original_starts_at)) . ' в ' . $e->original_starts_at->format('H:i'),
                'sub' => $e->reason ? 'Отменено: ' . $e->reason : 'Отменено',
                'unpaid' => false,
                'deletion' => false,
                'url' => null,
                'recordingUrl' => null,
            ]);

        return [
            'past' => $held->concat($cancelled)->sortByDesc(fn ($r) => $r['at']->timestamp)->take(30)->values(),
        ];
    }

    /** Отчёт о проведённом занятии (LsEnded, LsDeleteRequest). */
    private function report(User $teacher, Room $room, MeetingSession $s, ?array $next): array
    {
        $att = TeacherScheduleService::attendance($s);
        $minutes = TeacherScheduleService::sessionMinutes($s);
        $ended = $s->ended_at ?? $s->started_at;
        $schedule = $next['schedule'] ?? $room->schedules->first();

        $recording = $this->readyRecordings(collect([$s]))[$s->id] ?? null;
        $recordEnabled = ! empty($s->settings_snapshot['record']);

        $chat = Message::where('room_id', $room->id)
            ->whereBetween('created_at', [$s->started_at, $s->ended_at ?? $s->started_at->copy()->addHours(4)])
            ->count();

        $records = PaymentRecord::where('meeting_session_id', $s->id)->where('teacher_id', $teacher->id)
            ->with(['student:id,name', 'meetingSession:id,pricing_snapshot'])
            ->get();

        $sameDay = MeetingSession::where('room_id', $room->id)
            ->where('id', '!=', $s->id)
            ->where('status', 'completed')
            ->whereDate('started_at', $s->started_at->toDateString())
            ->orderBy('started_at')
            ->get();

        $attendedWord = $att['total'] > 1 ? 'учеников были' : 'ученик был';
        $polls = LessonActivityService::polls($s);
        $activity = LessonActivityService::participants($s);

        return [
            'sub' => self::facts([
                [$ended->isToday() ? 'Завершено сегодня в ' . $ended->format('H:i') : 'Прошло ' . HumanDate::day($s->started_at)],
                $s->started_at->format('H:i') . '–' . $ended->format('H:i'),
                TeacherScheduleService::repeatLabel($schedule),
            ]),
            'stats' => array_values(array_filter([
                ['value' => $minutes . ' мин', 'label' => 'длительность'],
                $att['total'] ? ['value' => $att['attended'] . ' из ' . $att['total'], 'label' => $attendedWord] : null,
                ['value' => (string) $chat, 'label' => plural_ru($chat, 'сообщение', 'сообщения', 'сообщений', false) . ' в чате'],
                $polls ? ['value' => (string) $polls, 'label' => plural_ru($polls, 'голосование', 'голосования', 'голосований', false)] : null,
            ])),
            'attendance' => $att['students']->map(fn (array $st) => $st + self::activityLine($st, $activity->get((string) $st['id']), $minutes)),
            'teacherMinutes' => $minutes,
            'recording' => $recording ? [
                'title' => 'Запись · ' . plural_ru($minutes, 'минута', 'минуты', 'минут'),
                'sub' => HumanDate::day($s->started_at) . ', ' . $s->started_at->format('H:i'),
                'url' => $this->recordingUrl($recording),
            ] : null,
            'recordingState' => match (true) {
                (bool) $recording => 'ready',
                $recordEnabled => 'processing',
                default => 'none',
            },
            'payments' => $records->map(fn (PaymentRecord $r) => [
                'id' => $r->id,
                'studentId' => $r->student_id,
                'name' => $r->student?->name,
                'amount' => $r->amount(),
                'status' => $r->status,
                'overdue' => $r->isOverdue(),
                'due' => $r->due_date ? HumanDate::date($r->due_date) : null,
                'justPaid' => in_array($r->id, $this->justPaid[$r->student_id] ?? [], true),
            ]),
            'nextInfo' => $next ? [
                'when' => HumanDate::at($next['start']),
                'sub' => collect([Str::ucfirst((string) TeacherScheduleService::repeatLabel($next['schedule'])), plural_ru((int) $next['start']->diffInMinutes($next['end']), 'минута', 'минуты', 'минут')])->filter()->implode(' · '),
            ] : null,
            'sameDay' => $sameDay->map(fn (MeetingSession $o) => [
                'id' => $o->id,
                'title' => $o->started_at->format('H:i') . '–' . ($o->ended_at ?? $o->started_at)->format('H:i') . ' · ' . plural_ru(TeacherScheduleService::sessionMinutes($o), 'минута', 'минуты', 'минут'),
                'url' => $this->lessonUrl($room->id, $o->id),
            ]),
            'deletion' => $s->deletion_requested_at ? [
                'at' => HumanDate::at($s->deletion_requested_at),
                'reason' => $s->deletion_reason,
            ] : null,
            'sessionTitle' => collect([$att['total'] === 1 ? $att['students']->first()['name'] : null, HumanDate::day($s->started_at) . ', ' . $s->started_at->format('H:i') . '–' . $ended->format('H:i')])->filter()->implode(' · '),
        ];
    }

    /**
     * Строка ученика в отчёте (LsEnded): «В классе 58 минут из 60 · с микрофоном 21 минуту · камера была включена»
     * и оценка активности «8 из 10». Без событий класса — просто «Был на занятии».
     *
     * @param  array{minutes:int, talk:int, camera:int, score:int}|null  $a
     */
    private static function activityLine(array $student, ?array $a, int $total): array
    {
        if (! $student['attended']) {
            return ['sub' => 'Не был на занятии', 'score' => null];
        }

        if (! $a || $a['minutes'] < 1) {
            return ['sub' => 'Был на занятии', 'score' => null];
        }

        return [
            'sub' => collect([
                'В классе ' . plural_ru(min($a['minutes'], $total), 'минуту', 'минуты', 'минут') . ' из ' . $total,
                $a['talk'] > 0 ? 'с микрофоном ' . plural_ru($a['talk'], 'минуту', 'минуты', 'минут') : null,
                $a['camera'] > 0 ? 'камера была включена' : 'без камеры',
            ])->filter()->implode(' · '),
            'score' => $a['score'] . ' из ' . LessonActivityService::MAX_SCORE,
        ];
    }

    /** Данные окон: перенос, отмена, план, завершение, ученики и название, презентации. */
    private function modals(Room $room, ?array $next, ?MeetingSession $session): array
    {
        $data = [];
        $student = $room->participants->count() === 1 ? $room->participants->first() : null;
        $name = $student ? ($student->first_name ?: $student->name) : null;
        $data['notifySub'] = $room->participants->isEmpty() ? null
            : ($name ? $name . ' получит уведомление' : 'Ученики получат уведомление') . ' в кабинете и на телефоне';

        if ($this->rescheduleOpen) {
            $occurrence = $this->rsScheduleId ? $this->occurrenceFor($room, $this->rsScheduleId, $this->rsAt) : null;
            $schedule = $occurrence['schedule'] ?? null;
            $following = $occurrence && $schedule->type !== 'once' && $this->rsScope === 'following';
            $weekly = $following || (! $occurrence && $this->rsRepeat === 'weekly');
            $repeat = $schedule && $schedule->type !== 'once' ? TeacherScheduleService::repeatLabel($schedule) : null;

            $first = $weekly
                ? TeacherLessonService::firstOccurrence('weekly', $this->rsDate, $this->rsTime, $this->rsDays)
                : TeacherLessonService::firstOccurrence('once', $this->rsDate, $this->rsTime);

            $data['rsMode'] = $occurrence ? ($following ? 'following' : 'one') : 'new';
            $data['rsSeries'] = (bool) $repeat;
            $data['rsScopes'] = $repeat ? [
                'one' => ['Только это занятие', 'Остальные — ' . $repeat . ', без изменений'],
                'following' => ['Это и все следующие', 'Сейчас — ' . $repeat . ' в ' . $occurrence['original']->format('H:i')],
            ] : [];
            $data['rsNew'] = $first
                ? ($weekly
                    ? TeacherScheduleService::repeatLabel(new RoomSchedule(['type' => 'recurring', 'recurrence_type' => 'weekly', 'recurrence_days' => $this->rsDays])) . ' в ' . $this->rsTime . ', с ' . HumanDate::date($first)
                    : HumanDate::at($first))
                : null;
            $data['rsDurations'] = TeacherLessonService::durationOptions($this->rsDuration);
            $data['rsCurrent'] = $occurrence ? HumanDate::at($occurrence['start']) : null;
        }

        if ($this->cancelOpen) {
            $occurrence = $this->occurrenceFor($room, $this->cancelScheduleId, $this->cancelAt);
            $schedule = $occurrence['schedule'] ?? null;
            $series = $schedule && $schedule->type !== 'once';
            $isSeries = $series && $this->cancelScope === 'series';

            $data['cancelSeries'] = $series;
            $data['cancelScopes'] = $series ? [
                'one' => ['Только это занятие', Str::ucfirst(HumanDate::at($occurrence['start']))],
                'series' => ['Всю серию', Str::ucfirst((string) TeacherScheduleService::repeatLabel($schedule)) . ' в ' . $occurrence['original']->format('H:i') . ', начиная с ' . (HumanDate::day($occurrence['original']) === 'сегодня' ? 'сегодня' : HumanDate::day($occurrence['original']))],
            ] : [];
            $data['cancelIsSeries'] = $isSeries;
            $data['cancelReasonHint'] = $room->participants->isEmpty() ? null
                : ($name ? $name . ' увидит причину в своём расписании' : 'Ученики увидят причину в своём расписании');
            // Архив — только если после отмены занятий в расписании не останется
            $data['cancelCanArchive'] = $schedule && ($isSeries || $schedule->type === 'once') && ! $this->hasOtherLessons($room, $schedule->id);
        }

        if ($this->editOpen) {
            $teacher = auth()->user();
            $options = app(TeacherLessonService::class)->participantOptions($teacher, $room);
            $search = mb_strtolower(trim($this->editSearch));
            $count = count($this->editStudents);
            $tariff = $teacher->activeSubscription()?->tariff;

            $data['editOptions'] = $search === '' ? $options : $options->filter(
                fn (User $u) => in_array($u->id, $this->editStudents, true) || str_contains(mb_strtolower($u->name), $search)
            );
            $data['editSearchable'] = $options->count() > 8;
            $data['editHint'] = match (true) {
                $options->isEmpty() => 'Пока некого выбрать — пригласите ученика в разделе «Ученики»',
                $this->editKind === 'group' && $count > 0 => plural_ru($count, 'ученик', 'ученика', 'учеников')
                    . ($count === 1 ? ' — пока занятие считается индивидуальным' : '')
                    . ($tariff?->max_participants ? ' · на «' . $tariff->name . '» до ' . $tariff->max_participants . ' в занятии' : ''),
                default => 'Нового ученика сначала пригласите в разделе «Ученики»',
            };
            $current = $room->participants->pluck('id')->map(fn ($id) => (int) $id)->all();
            $added = array_diff($this->editStudents, $current);
            $data['editNote'] = $added ? (count($added) === 1 ? 'Новый ученик получит' : 'Новые ученики получат') . ' уведомление и задания занятия' : null;
        }

        if ($this->presentationsOpen) {
            $data['presentationAccept'] = TeacherLessonService::PRESENTATION_ACCEPT;
            $data['materialsLink'] = Route::has('cabinet.teacher.materials') ? route('cabinet.teacher.materials') : url('/tutor/materials');
        }

        if ($this->confirmPresentation) {
            $path = $this->presentationPath($room, $this->confirmPresentation);
            $data['confirmPresentationName'] = $path ? TeacherLessonService::presentationName($room, $path) : null;
        }

        if ($this->confirmStop) {
            $lessonType = $room->user?->lessonTypes()->where('type', $room->type ?? 'individual')->first();
            $price = $room->participants->count() === 1 ? $room->getEffectivePrice($room->participants->first()->id) : null;
            $data['stopNote'] = collect([
                'Запись появится в «Записях»',
                ! $lessonType?->isMonthly() && $price ? 'ученику начислится ' . \App\Support\Money::format($price) . ', если он был на занятии' : null,
            ])->filter()->implode(', ') . '.';
        }

        return $data;
    }
}
