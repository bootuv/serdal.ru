<?php

namespace App\Demo\Teacher;

use App\Demo\Concerns\StudentBook;
use App\Demo\Concerns\TeacherModals;
use App\Demo\Screen;
use App\Demo\World;
use App\Support\HumanDate;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * «Ученики» учителя — App\Livewire\Cabinet\Teacher\Students.
 *
 * Состояние в адресе: tab (students | groups), filter (all | debt | none), search;
 * окно «Пригласить ученика» — invite=1, inviteTab, findQuery, pickId;
 * «Отметить оплату» — mark=id, markSelected, markDone=1 (оплата отмечена: строка «Оплачено» с «Отменить»);
 * «Продлить срок оплаты» — extend=id, extendDays.
 */
class Students extends Screen
{
    use StudentBook, TeacherModals {
        TeacherModals::modalParams as baseModalParams;
    }

    public const PATH = 'students';

    public const EXAMPLES = [
        'students', 'students?invite=1', 'students?invite=1&inviteTab=find&findQuery=за&pickId=112',
        'students?tab=groups', 'students?filter=debt', 'students?search=адам', 'students?filter=none',
        'students?mark=103', 'students?mark=103&markDone=1', 'students?mark=104', 'students?extend=103&extendDays=7',
    ];

    public string $view = 'livewire.cabinet.teacher.students';

    public string $title = 'Ученики';

    public ?string $active = 'students';

    public function modalParams(): array
    {
        return [...$this->baseModalParams(), 'invite', 'inviteTab', 'findQuery', 'pickId', 'extend', 'extendDays', 'markDone'];
    }

    public function actions(): array
    {
        $mark = $this->state('mark', 0);
        $selected = $this->stateInts('markSelected', $mark ? $this->unpaidOf($mark)->pluck('id')->all() : []);
        $sum = $mark ? (int) $this->unpaidOf($mark)->whereIn('id', $selected)->sum('amount') : 0;
        $pick = $this->state('pickId', 0);
        $pickName = collect(self::AVAILABLE)->get($pick);

        return [
            'openInvite' => ['set' => ['invite' => '1', 'inviteTab' => '{0}', 'findQuery' => null, 'pickId' => null]],
            'closeInvite' => ['close' => true],
            'sendInvite' => ['close' => true, 'toast' => 'Приглашение отправлено на почту ученика'],
            'addStudent' => $pickName
                ? ['close' => true, 'set' => ['tab' => null, 'filter' => null], 'toast' => $pickName[0] . ' ' . $pickName[1] . ' теперь в вашем списке']
                : ['toast' => 'Выберите ученика из списка', 'tone' => 'danger'],
            'markPaid' => ['set' => ['mark' => '{0}', 'markSelected' => null, 'markDone' => null]],
            'confirmMarkPaid' => $selected
                ? ['set' => ['markDone' => '1'], 'toast' => 'Оплата отмечена' . ($sum ? ': ' . Money::format($sum) : '')]
                : ['toast' => 'Отметьте хотя бы одно занятие', 'tone' => 'danger'],
            'undoPaid' => ['close' => true, 'toast' => 'Отметка об оплате отменена'],
            'remind' => ['toast' => 'Напомнили'],
            'openExtend' => ['set' => ['extend' => '{0}', 'extendDays' => null]],
            'closeExtend' => ['close' => true],
            'extend' => ['close' => true, 'toast' => ($e = $this->extendDue()) ? 'Срок продлён до ' . HumanDate::date($e) : 'Продлевать нечего — долгов нет'],
        ] + $this->modalActions();
    }

    public function data(): array
    {
        $tab = $this->state('tab', 'students');
        $filter = $this->state('filter', 'all');
        $search = $this->state('search', $this->state('q', ''));

        $modal = $this->modalData();
        [$paid, $flash] = $this->justPaid($modal);

        $all = $this->studentRows($paid);
        $groups = $this->groupRows($all);

        $needle = mb_strtolower(trim($search));
        $matches = fn (array $row) => $needle === '' || collect($row['haystack'])->contains(fn ($v) => $v && str_contains(mb_strtolower($v), $needle));

        $rows = $all->filter($matches)->filter(fn (array $r) => match ($filter) {
            'debt' => $r['owes'],
            'none' => $r['none'],
            default => true,
        })->values();

        return [
            'tab' => $tab,
            'filter' => $filter,
            'search' => $search,
            'isEmpty' => false,
            'sub' => implode(' · ', [
                plural_ru($all->count(), 'ученик', 'ученика', 'учеников'),
                plural_ru($groups->count(), 'группа', 'группы', 'групп'),
            ]),
            'rows' => $rows,
            'groups' => $groups->filter($matches)->values(),
            'filters' => [
                'all' => 'Все',
                'debt' => 'Ждут оплаты · ' . $all->where('owes', true)->count(),
                'none' => 'Без занятий · ' . $all->where('none', true)->count(),
            ],
            'debts' => $this->debts($all, $paid),
            'debtTotalLabel' => $this->sumLabel($all->flatMap(fn ($r) => $r['owes'] ? $r['unpaid'] : [])) ?? '',
            'invite' => $this->state('invite', false) ? $this->inviteData() : null,
            'inviteOpen' => $this->state('invite', false),
            'inviteTab' => $this->state('inviteTab', 'link'),
            'inviteEmail' => '',
            'findQuery' => $this->state('findQuery', ''),
            'pickId' => $this->state('pickId', 0) ?: null,
            'extend' => $this->extendData($all),
            'extendStudentId' => $this->state('extend', 0) ?: null,
            'extendDays' => $this->state('extendDays', 3),
            'flash' => $flash,
        ] + $modal;
    }

    /** Неоплаченные начисления ученика (без убранных из списка). */
    private function unpaidOf(int $id): Collection
    {
        return isset(World::STUDENTS[$id]) ? $this->unpaid($id) : collect();
    }

    /**
     * Только что отмеченная оплата (как MarksPayments::$justPaid): [id ученика => id начислений] и тост.
     * Одно начисление отмечается сразу, без окна (как настоящий markPaid), несколько — после «Отметить оплату» в окне.
     */
    private function justPaid(array &$modal): array
    {
        $id = $this->state('mark', 0);
        $unpaid = $this->unpaidOf($id);
        if ($unpaid->isEmpty()) {
            return [[], null];
        }

        $single = $unpaid->count() === 1;
        if (! $single && ! $this->state('markDone', false)) {
            $modal['markRows'] = $unpaid->map(fn (array $r) => [
                'id' => $r['id'],
                'title' => $r['title'],
                'hint' => ($r['overdue'] ? 'срок был до ' : 'оплатить до ') . HumanDate::date($r['due']),
                'overdue' => $r['overdue'],
                'amount' => $r['amount'],
            ])->all();
            $selected = $this->stateInts('markSelected', $unpaid->pluck('id')->all());
            $modal['markSelected'] = $selected;
            $modal['markSum'] = $unpaid->whereIn('id', $selected)->sum('amount');

            return [[], null];
        }

        // Окно закрыто: оплата отмечена
        $modal['markStudentId'] = null;
        $ids = $single ? $unpaid->pluck('id')->all() : $this->stateInts('markSelected', $unpaid->pluck('id')->all());
        $sum = (int) $unpaid->whereIn('id', $ids)->sum('amount');

        return [[$id => $ids], $single ? 'Оплата отмечена' . ($sum ? ': ' . Money::format($sum) : '') : null];
    }

    /** Строки учеников (Students::studentRows). */
    private function studentRows(array $paid): Collection
    {
        $removed = $this->stateInts('removed');

        return World::students()
            ->reject(fn ($s) => in_array($s->id, $removed, true))
            ->sortBy('name')
            ->map(function ($s) use ($paid) {
                $id = $s->id;
                $student = $this->pupil($id);
                $unpaid = $this->unpaid($id)->reject(fn (array $r) => in_array($r['id'], $paid[$id] ?? [], true))->values();
                $state = match (true) {
                    $unpaid->isNotEmpty() => $unpaid->contains('overdue', true) ? 'overdue' : 'unpaid',
                    default => 'paid',
                };
                $rooms = collect($this->roomIds($id));
                $labels = $rooms->map(fn (int $r) => $this->isGroupRoom($r) ? 'группа «' . World::ROOMS[$r][0] . '»' : World::ROOMS[$r][0]);
                $next = $this->nextLesson($id);

                return [
                    'id' => $id,
                    'user' => $student,
                    'name' => $student->name,
                    'email' => $student->email,
                    'firstName' => $this->firstName($id),
                    'href' => route('cabinet.teacher.student', ['student' => $id]),
                    'sub' => $labels->take(2)->implode(' · '),
                    'none' => $rooms->isEmpty(),
                    'next' => $next ? $this->nextView($next, true) : null,
                    'state' => $state,
                    'unpaid' => $unpaid,
                    'owes' => $state !== 'paid',
                    'payNote' => $state !== 'paid' ? $this->debtLabel($unpaid) : null,
                    'haystack' => [$student->name, $student->email, $student->phone],
                ];
            })
            ->values();
    }

    /** Группы: занятия с несколькими учениками (Students::groupRows). */
    private function groupRows(Collection $students): Collection
    {
        $byId = $students->keyBy('id');

        return collect(World::ROOMS)
            ->filter(fn (array $r) => $r[1] === 'group')
            ->map(function (array $room, int $roomId) use ($byId) {
                $people = collect($room[2]);
                $next = World::lessons(Carbon::now(), Carbon::now()->addDays(14))->first(fn ($l) => ! $l['past'] && $l['roomId'] === $roomId);
                $durations = collect($room[3])->countBy(fn ($s) => $s[2])->sortDesc()->keys();

                return [
                    'id' => $roomId,
                    'name' => $room[0],
                    'people' => $people->map(fn (int $id) => $this->firstName($id))->implode(', '),
                    'next' => $next ? $this->nextView($next, false) : null,
                    'schedule' => World::repeatLabel($roomId) . ' · ' . plural_ru($durations->first(), 'минута', 'минуты', 'минут'),
                    'overdue' => $people->filter(fn (int $id) => ($byId[$id]['state'] ?? null) === 'overdue')->count(),
                    'unpaid' => $people->filter(fn (int $id) => ($byId[$id]['state'] ?? null) === 'unpaid')->count(),
                    'href' => $this->lessonUrl($roomId),
                    'haystack' => [$room[0], ...$people->map(fn (int $id) => World::student($id)->name)->all()],
                ];
            })
            ->values();
    }

    /** «Сегодня в 16:00» + через сколько (если скоро) или название занятия (Students::nextView). */
    private function nextView(array $l, bool $withRoom): array
    {
        $soon = $l['start']->isFuture() && $l['start']->lte(Carbon::now()->addHours(3));
        $label = $l['group'] ? 'группа «' . $l['title'] . '»' : $l['title'];

        return [
            'when' => Str::ucfirst(HumanDate::at($l['start'])),
            'note' => $soon ? HumanDate::until($l['start']) : ($withRoom ? $label : null),
            'urgent' => $soon,
        ];
    }

    /** Фокус «Ждут оплаты» (Students::debts). */
    private function debts(Collection $all, array $paid): Collection
    {
        return $all
            ->filter(fn ($r) => $r['owes'] || isset($paid[$r['id']]))
            ->sortBy(fn ($r) => [isset($paid[$r['id']]) ? 1 : 0, $r['state'] === 'overdue' ? 1 : 2, $r['unpaid']->first()['due']->timestamp ?? PHP_INT_MAX])
            ->map(function ($r) use ($paid) {
                if (! $r['owes']) {
                    return ['row' => $r, 'paid' => true, 'undo' => true, 'meta' => 'Оплата отмечена сейчас', 'note' => null, 'overdue' => false, 'claim' => null];
                }

                $first = $r['unpaid']->first();
                $overdue = $r['state'] === 'overdue';
                $status = $overdue ? $this->debtStatus($r['id']) : null;

                return [
                    'row' => $r,
                    'paid' => false,
                    'undo' => isset($paid[$r['id']]),
                    'overdue' => $overdue,
                    'meta' => $this->debtLabel($r['unpaid'])
                        . ($overdue ? ' · срок был ' . HumanDate::date($first['due']) : ' · до ' . HumanDate::date($first['due'])),
                    'note' => $status
                        ? 'Ещё ' . plural_ru($status['lessons_left'], 'занятие', 'занятия', 'занятий') . ' с долгом — и ' . $r['firstName'] . ' не сможет войти в ваши занятия.'
                        : null,
                    'claim' => null,
                ];
            })
            ->values();
    }

    private function inviteData(): array
    {
        $q = trim($this->state('findQuery', ''));

        return [
            // Не настоящая подписанная ссылка: она привязала бы зарегистрировавшегося к реальному пользователю с этим id
            'link' => url('/register/invite?teacher=demo'),
            'results' => $q === '' ? collect() : $this->availableStudents($q),
            'query' => $q,
        ];
    }

    /** Новый срок в окне «Продлить срок оплаты»: просроченный считаем от сегодня, иначе от текущего срока. */
    private function extendDue(): ?Carbon
    {
        $first = $this->unpaidOf($this->state('extend', 0))->first();
        if (! $first) {
            return null;
        }

        $days = $this->state('extendDays', 3);

        return $first['overdue'] ? Carbon::today()->addDays($days) : $first['due']->copy()->addDays($days);
    }

    private function extendData(Collection $all): ?array
    {
        $row = $all->firstWhere('id', $this->state('extend', 0));
        $first = $row ? $row['unpaid']->first() : null;
        if (! $first) {
            return null;
        }

        return [
            'sub' => $row['name'] . ' · ' . $this->debtLabel($row['unpaid']),
            'newDate' => 'до ' . HumanDate::day($this->extendDue()),
            'explain' => $first['overdue']
                ? 'Срок прошёл ' . HumanDate::date($first['due']) . ' — считаем от сегодня. До новой даты вход в занятия не закроется.'
                : 'Считаем от текущего срока — ' . HumanDate::day($first['due']) . '. Напоминание сдвинется.',
        ];
    }
}
