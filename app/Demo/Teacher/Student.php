<?php

namespace App\Demo\Teacher;

use App\Demo\Concerns\StudentBook;
use App\Demo\Screen;
use App\Demo\World;
use App\Services\TeacherStudentsService;
use App\Support\HumanDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Карточка ученика — App\Livewire\Cabinet\Teacher\Student, для любого из учеников 101–108.
 *
 * Состояние в адресе: tab (overview | lessons | tasks | pay); окно modal (mark | waive | extend | settings | assign | remove | undo)
 * с selected, extendDays, settingsFree, settingsType, roomIds, undo; paid — только что отмеченные начисления («Отменить»).
 */
class Student extends Screen
{
    use StudentBook;

    public const PATH = 'students/{id}';

    public const EXAMPLES = [
        'students/101', 'students/102?tab=lessons', 'students/103', 'students/103?tab=pay', 'students/103?tab=lessons',
        'students/103?tab=tasks', 'students/103?modal=mark', 'students/103?modal=waive&tab=pay', 'students/103?modal=extend&extendDays=7',
        'students/103?paid=203275', 'students/104?tab=pay', 'students/105?modal=settings', 'students/106?tab=pay', 'students/107?modal=assign',
        'students/108?modal=remove', 'students/101?tab=pay&modal=undo&undo=1', 'students/999',
    ];

    public string $view = 'livewire.cabinet.teacher.student';

    public string $title = 'Ученик';

    public ?string $active = 'students';

    public function modalParams(): array
    {
        return ['modal', 'selected', 'extendDays', 'settingsFree', 'settingsType', 'roomIds', 'undo'];
    }

    /** Ученик карточки; неизвестный адрес — первый ученик (страница 404 сайта ходит в базу). */
    private function id(): int
    {
        $id = (int) $this->param('id');

        return isset(World::STUDENTS[$id]) ? $id : 101;
    }

    private function selected(): array
    {
        return $this->stateInts('selected', $this->unpaid($this->id())->pluck('id')->all());
    }

    public function actions(): array
    {
        $id = $this->id();
        $unpaid = $this->unpaid($id);
        $selected = array_values(array_intersect($unpaid->pluck('id')->all(), $this->selected()));
        $sum = $this->sumLabel($unpaid->whereIn('id', $selected));
        $allOn = count($selected) === $unpaid->count();
        $rooms = $this->stateInts('roomIds', $this->roomIds($id));
        sort($rooms);
        $own = $this->roomIds($id);
        sort($own);
        $name = World::student($id)->name;

        return [
            'openModal' => ['set' => ['modal' => '{0}', 'selected' => null, 'extendDays' => null, 'settingsFree' => null, 'settingsType' => null, 'roomIds' => null]],
            'closeModal' => ['close' => true],
            'toggleAllRecords' => ['set' => ['selected' => $allOn ? '' : null]],
            'markPaid' => $selected
                ? ['close' => true, 'set' => ['paid' => implode(',', $selected)], 'toast' => 'Оплата отмечена' . ($sum ? ' · ' . $sum : '')]
                : ['toast' => 'Отметьте хотя бы одно занятие', 'tone' => 'danger'],
            'undoPaid' => ['set' => ['paid' => null], 'toast' => 'Отметка оплаты отменена'],
            'askUndoPaid' => ['set' => ['modal' => 'undo', 'undo' => '{0}']],
            'confirmUndoPaid' => ['close' => true, 'toast' => 'Отметка оплаты отменена'],
            'waive' => $selected
                ? ['close' => true, 'toast' => 'Оплата не требуется']
                : ['toast' => 'Отметьте хотя бы одно занятие', 'tone' => 'danger'],
            'extend' => ['close' => true, 'toast' => ($due = $this->extendDue($unpaid)) ? 'Срок продлён до ' . HumanDate::date($due) : 'Продлевать нечего — долгов нет'],
            'saveSettings' => ['close' => true, 'set' => ['paid' => null], 'toast' => match (true) {
                $this->state('settingsFree', false) => 'Сохранено: ученик занимается бесплатно',
                $this->state('settingsType', 'default') !== 'default' => 'Условия оплаты сохранены',
                default => 'Изменений нет',
            }],
            'saveRooms' => ['close' => true, 'toast' => $rooms === $own ? 'Изменений нет' : 'Занятия ученика сохранены'],
            'requestReview' => ['toast' => 'Отправили просьбу об отзыве: ' . $name],
            'remove' => ['go' => route('cabinet.teacher.students', ['removed' => $id]), 'toast' => $name . ' больше не в вашем списке'],
            'remind' => ['toast' => 'Напомнили'],
        ];
    }

    public function data(): array
    {
        $id = $this->id();
        $student = $this->pupil($id);
        $this->title = $student->name;

        $tab = $this->state('tab', 'overview');
        if (! in_array($tab, ['overview', 'lessons', 'tasks', 'pay'], true)) {
            $tab = 'overview';
        }
        $modal = $this->state('modal');

        $paidIds = $this->stateInts('paid');
        $all = $this->unpaid($id);
        $unpaid = $all->reject(fn (array $r) => in_array($r['id'], $paidIds, true))->values();
        $paidNow = $all->filter(fn (array $r) => in_array($r['id'], $paidIds, true))->values();

        $rooms = collect($this->roomIds($id));
        $next = $this->nextLesson($id);
        $nextView = $next ? ['when' => $next['running'] ? 'идёт сейчас' : HumanDate::at($next['start']), 'href' => $this->lessonUrl($next['roomId'])] : null;
        $overdue = $unpaid->contains('overdue', true);
        $performance = $this->performance($id);

        return [
            'pupil' => $student,
            'tab' => $tab,
            'modal' => $modal,
            'selected' => $this->selected(),
            'extendDays' => $this->state('extendDays', 3),
            'settingsFree' => $this->state('settingsFree', false),
            'settingsType' => $this->state('settingsType', 'default'),
            'roomIds' => $this->stateInts('roomIds', $this->roomIds($id)),
            'justPaid' => $paidIds,
            'undoRecordId' => $this->state('undo', 0) ?: null,
            'claimId' => null,
            'claimRejecting' => false,
            'claimReason' => '',

            'student' => $student,
            'claim' => null,
            'canRemind' => $overdue,
            'firstName' => $this->firstName($id),
            'facts' => [
                $rooms->map(fn (int $r) => World::ROOMS[$r][0])->take(2)->implode(', ')
                    . ' · ' . ($rooms->contains(fn (int $r) => $this->isGroupRoom($r)) ? 'в группе' : 'индивидуально'),
                'с ' . HumanDate::date($this->since($id)),
            ],
            'since' => HumanDate::date($this->since($id)),
            'next' => $nextView,
            'backUrl' => route('cabinet.teacher.students'),
            'chatUrl' => route('cabinet.teacher.messages', ['chat' => $id]),
            // В демо «Запланировать занятие» открывает окно на «Расписании» (TeacherModals: open=plan)
            'planUrl' => route('cabinet.teacher.schedule', ['open' => 'plan', 'planStudentId' => $id]),
            'taskNewUrl' => route('cabinet.teacher.task-new', ['student' => $id]),
            'scheduleUrl' => route('cabinet.teacher.schedule'),
            'pricesUrl' => route('cabinet.teacher.profile', ['tab' => 'prices']),
            'tabCounts' => ['pay' => $unpaid->count()],
            'isFree' => false,
            'dues' => $unpaid->map(fn (array $r) => $this->dueRow($r)),
            'dueSum' => $this->sumLabel($unpaid),
            'dueCount' => plural_ru($unpaid->count(), 'занятие', 'занятия', 'занятий'),
            'dueOverdue' => $overdue,
            'paidNow' => $paidNow->map(fn (array $r) => ['title' => $r['title'], 'amount' => TeacherStudentsService::rub($r['amount'])]),
            'rooms' => $rooms,
            'roomsLine' => $rooms->map(fn (int $r) => World::ROOMS[$r][0])->implode(', '),
            'contacts' => $this->contacts($student),
            'reviewAsk' => $this->reviewAsk($id),
            'metrics' => $performance['metrics'],
            'perfSub' => $performance['perfSub'],
            'homework' => $this->homework($id),
            'upcoming' => $this->upcoming($id),
            'past' => $this->past($id, $unpaid),
            'history' => $this->history($id, $paidNow),
            'terms' => $this->terms($id),
            'debtStatus' => $overdue ? $this->debtStatus($id) : null,
            'modalData' => $this->modalData($id, $modal, $unpaid, $nextView),
            'claimView' => null,
        ];
    }

    private function dueRow(array $r): array
    {
        return [
            'id' => $r['id'],
            'title' => $r['title'],
            'overdue' => $r['overdue'],
            'hint' => ($r['overdue'] ? 'Срок был ' : 'Оплатить до ') . HumanDate::date($r['due']),
            'amount' => TeacherStudentsService::rub($r['amount']),
        ];
    }

    /** Контакты: почта, Telegram, телефон (Student::contacts). */
    private function contacts($student): array
    {
        return array_values(array_filter([
            ['label' => $student->email, 'href' => 'mailto:' . $student->email, 'external' => false],
            $student->telegram ? ['label' => 'Telegram · @' . $student->telegram, 'href' => 'https://t.me/' . $student->telegram, 'external' => true] : null,
            $student->phone ? ['label' => $student->phone, 'href' => 'tel:' . preg_replace('/[^0-9+]/', '', $student->phone), 'external' => false] : null,
        ]));
    }

    /** Предстоящие занятия на 30 дней, не больше пяти (Student::upcoming). */
    private function upcoming(int $id): Collection
    {
        return World::lessons(Carbon::now()->startOfDay(), Carbon::now()->addDays(30))
            ->filter(fn (array $l) => ! $l['past'] && $l['participants']->contains('id', $id))
            ->take(5)
            ->map(fn (array $l) => [
                'key' => $l['key'],
                'time' => $l['start']->format('H:i'),
                'title' => Str::ucfirst(HumanDate::day($l['start'])) . ' · ' . $l['title'],
                'soon' => $l['running'] ? 'Идёт сейчас' : ($l['start']->isToday() ? 'Начнётся ' . HumanDate::until($l['start']) : null),
                'meta' => implode(' · ', [plural_ru($l['duration'], 'минута', 'минуты', 'минут'), $l['repeat']]),
                'today' => $l['start']->isToday() || $l['running'],
                'running' => $l['running'],
                'href' => $this->lessonUrl($l['roomId']),
                'startUrl' => route('cabinet.teacher.lesson', ['room' => $l['roomId'], 'class' => 1]),
            ])
            ->values();
    }

    /** Прошедшие занятия: посещение, длительность, активность; неоплаченные — бейджем (Student::past). */
    private function past(int $id, Collection $unpaid): array
    {
        $history = $this->pastLessons($id);
        $unpaidStarts = $unpaid->map(fn (array $r) => $r['start']->timestamp)->all();

        return [
            'total' => $history->count(),
            'missed' => $history->where('attended', false)->count(),
            'rows' => $history->take(20)->map(fn (array $l) => [
                'key' => $l['key'],
                'time' => $l['start']->format('H:i'),
                'title' => Str::ucfirst(HumanDate::day($l['start'])) . ' · ' . $l['title'],
                'sub' => $l['attended']
                    ? plural_ru($l['minutes'], 'минута', 'минуты', 'минут') . ' · активность ' . $l['activity'] . ' из 10'
                    : 'Не было на занятии',
                'missed' => ! $l['attended'],
                'unpaid' => in_array($l['start']->timestamp, $unpaidStarts, true),
                'href' => $this->lessonUrl($l['roomId']),
            ])->values(),
        ];
    }

    /** История оплат + только что отмеченные сверху. */
    private function history(int $id, Collection $paidNow): Collection
    {
        return $paidNow->map(fn (array $r) => [
            'id' => $r['id'],
            'title' => $r['title'],
            'waived' => false,
            'sub' => 'Оплачено ' . HumanDate::date(Carbon::today()),
            'amount' => TeacherStudentsService::rub($r['amount']),
            'canUndo' => true,
        ])->concat($this->payHistory($id))->values();
    }

    private function extendDue(Collection $unpaid): ?Carbon
    {
        $first = $unpaid->first();
        if (! $first) {
            return null;
        }
        $days = $this->state('extendDays', 3);

        return $first['overdue'] ? Carbon::today()->addDays($days) : $first['due']->copy()->addDays($days);
    }

    private function modalData(int $id, ?string $modal, Collection $unpaid, ?array $next): ?array
    {
        $selected = $this->selected();

        return match ($modal) {
            'mark', 'waive' => [
                'rows' => $unpaid->map(fn (array $r) => $this->dueRow($r)),
                'allOn' => $unpaid->isNotEmpty() && $unpaid->every(fn (array $r) => in_array($r['id'], $selected, true)),
                'total' => ($sel = $unpaid->whereIn('id', $selected))->isNotEmpty() ? $this->sumLabel($sel) : null,
            ],
            'extend' => ($first = $unpaid->first()) ? [
                'sub' => World::student($id)->name . ' · ' . $this->sumLabel($unpaid),
                'newDate' => 'до ' . HumanDate::day($this->extendDue($unpaid)),
                'explain' => $first['overdue']
                    ? 'Срок прошёл ' . HumanDate::date($first['due']) . ' — считаем от сегодня. До новой даты вход в занятия не закроется.'
                    : 'Считаем от текущего срока — ' . HumanDate::day($first['due']) . '. Напоминание сдвинется.',
            ] : null,
            'undo' => $this->undoData($id),
            'assign' => [
                'rooms' => collect(World::ROOMS)->map(fn (array $r, int $roomId) => [
                    'id' => $roomId,
                    'name' => $r[0],
                    'group' => $r[1] === 'group',
                    'sub' => ($r[1] === 'group' ? 'Групповое · ' . plural_ru(count($r[2]), 'ученик', 'ученика', 'учеников') : 'Индивидуальное')
                        . ' · ' . World::repeatLabel($roomId),
                ])->values(),
            ],
            'remove' => [
                'next' => $next,
                'debt' => $unpaid->isNotEmpty() ? $this->sumLabel($unpaid) : null,
            ],
            'settings' => [
                'debt' => $unpaid->isNotEmpty() ? plural_ru($unpaid->count(), 'занятие', 'занятия', 'занятий') . ' (' . $this->sumLabel($unpaid) . ')' : null,
            ],
            default => [],
        };
    }

    /** Окно «Отменить оплату?» для записи из истории (undo — id записи; неизвестный — первая отменяемая). */
    private function undoData(int $id): ?array
    {
        $history = $this->history($id, collect())->where('canUndo', true);
        $row = $history->firstWhere('id', $this->state('undo', 0)) ?? $history->first();
        if (! $row) {
            return null;
        }

        return [
            'title' => $row['title'],
            'sub' => 'Отмечено ' . Str::before(Str::after($row['sub'], 'Оплачено '), ','),
            'amount' => $row['amount'],
            'explain' => 'Занятие снова будет в долгах. Срок оплаты прошёл — если не продлить его, вход в занятия для ученика закроется.',
        ];
    }
}
