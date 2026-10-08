<?php

namespace App\Services;

use App\Models\MeetingSession;
use App\Models\Review;
use App\Models\Room;
use App\Models\SubscriptionPayment;
use App\Models\SupportMessage;
use App\Models\TeacherApplication;
use App\Models\User;
use App\Support\HumanDate;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * «Сегодня» администратора: очередь исключений, идущие занятия, итоги периода и график занятий по дням.
 * Оплату занятий между учениками и учителями не показываем — только платежи учителей за тариф.
 */
class AdminTodayService
{
    public const PERIODS = [7, 30, 90];

    /**
     * Что ждёт решения: обращения, заявки, зависшие платежи, жалобы на отзывы, запросы на удаление.
     *
     * @return array<int, array{title:string, who:string, em:?string, n:int, href:string}>
     */
    public function queue(): array
    {
        return array_values(array_filter([
            $this->supportItem(),
            $this->applicationsItem(),
            $this->paymentsItem(),
            $this->reviewsItem(),
            $this->deletionsItem(),
        ]));
    }

    private function supportItem(): ?array
    {
        $unread = SupportMessage::query()
            ->whereNull('read_at')
            ->join('support_chats', 'support_chats.id', '=', 'support_messages.support_chat_id')
            ->whereColumn('support_messages.user_id', 'support_chats.user_id')
            ->whereHas('user', fn ($q) => $q->where('role', '!=', User::ROLE_ADMIN))
            ->select('support_messages.*')
            ->with('user:id,name')
            ->orderBy('support_messages.created_at')
            ->get();
        if ($unread->isEmpty()) {
            return null;
        }

        $people = $unread->pluck('user')->filter()->unique('id');

        return [
            'title' => 'Обращения в поддержку',
            'who' => $this->names($people->pluck('name')),
            'em' => 'самое давнее ждёт ' . $this->waited($unread->first()->created_at),
            'n' => $people->count(),
            'href' => $this->route('support', ['filter' => 'unread']),
        ];
    }

    private function applicationsItem(): ?array
    {
        $pending = TeacherApplication::where('status', TeacherApplication::STATUS_PENDING)->orderBy('created_at')->get();
        if ($pending->isEmpty()) {
            return null;
        }
        $days = (int) $pending->first()->created_at->copy()->startOfDay()->diffInDays(today());

        return [
            'title' => 'Заявки учителей',
            'who' => $this->names($pending->map(fn (TeacherApplication $a) => trim($a->first_name . ' ' . $a->last_name))),
            'em' => $days >= 1 ? ($pending->count() > 1 ? 'первая ждёт ' : 'ждёт ') . plural_ru($days, 'день', 'дня', 'дней') : null,
            'n' => $pending->count(),
            'href' => $this->route('applications'),
        ];
    }

    /** Платежи за тариф «ожидает оплаты» дольше суток (как во вкладке «Платежи»). */
    public function stuckPayments(): Collection
    {
        return SubscriptionPayment::query()
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->whereBetween('created_at', [now()->subDays(30), now()->subDay()])
            ->whereNull('meta->card_binding')
            ->with(['user:id,name', 'tariff'])
            ->orderBy('created_at')
            ->get();
    }

    private function paymentsItem(): ?array
    {
        $stuck = $this->stuckPayments();
        if ($stuck->isEmpty()) {
            return null;
        }

        return [
            'title' => 'Платежи ждут оплаты больше суток',
            'who' => $stuck->take(2)->map(fn (SubscriptionPayment $p) => ($p->user?->name ?? 'Учитель') . ', '
                . ($p->isExtraLessons() ? 'занятия сверх тарифа' : '«' . ($p->tariff?->name ?? 'тариф') . '»') . ' ' . Money::format((int) $p->amount))
                ->implode('; ') . ($stuck->count() > 2 ? ' и ещё ' . ($stuck->count() - 2) : ''),
            'em' => 'с ' . HumanDate::day($stuck->first()->created_at),
            'n' => $stuck->count(),
            'href' => $this->route('payments', ['tab' => 'payments']),
        ];
    }

    private function reviewsItem(): ?array
    {
        $reported = Review::where('is_reported', true)->where('is_rejected', false)->with('teacher:id,name')->orderBy('reported_at')->get();
        if ($reported->isEmpty()) {
            return null;
        }

        return [
            'title' => 'Жалобы на отзывы',
            'who' => $reported->take(2)->map(fn (Review $r) => ($r->teacher?->name ?? 'Учитель')
                . ($r->report_reason_label ? ': ' . mb_strtolower($r->report_reason_label) : ''))->implode('; ')
                . ($reported->count() > 2 ? ' и ещё ' . ($reported->count() - 2) : ''),
            'em' => null,
            'n' => $reported->count(),
            'href' => $this->route('reviews', ['tab' => AdminReviewsService::TAB_REPORTS]),
        ];
    }

    private function deletionsItem(): ?array
    {
        $requests = MeetingSession::whereNotNull('deletion_requested_at')
            ->with(['room' => fn ($q) => $q->withTrashed()->with('user:id,name')])
            ->orderBy('deletion_requested_at')
            ->get();
        if ($requests->isEmpty()) {
            return null;
        }

        return [
            'title' => 'Запросы на удаление занятий',
            'who' => $requests->take(2)->map(fn (MeetingSession $s) => ($s->room?->user?->name ?? 'Учитель') . ' — занятие '
                . HumanDate::day($s->started_at) . ($s->room?->name ? ', ' . $s->room->name : ''))->implode('; ')
                . ($requests->count() > 2 ? ' и ещё ' . ($requests->count() - 2) : ''),
            'em' => null,
            'n' => $requests->count(),
            'href' => $this->route('lessons', ['tab' => 'deletions']),
        ];
    }

    /**
     * Занятия, которые идут сейчас: учитель, ученик или группа, сколько идёт; дольше плана — жирным.
     *
     * @return Collection<int, array>
     */
    public function live(): Collection
    {
        $rooms = Room::where('is_running', true)->with(['user:id,name', 'participants:id,name', 'schedules'])->orderBy('id')->get();
        $sessions = MeetingSession::whereIn('room_id', $rooms->pluck('id'))->where('status', 'running')
            ->orderByDesc('started_at')->get()->unique('room_id')->keyBy('room_id');

        return $rooms->map(function (Room $room) use ($sessions) {
            $count = $room->participants->count();
            $group = $room->type === 'group' || $count > 1;
            $started = $sessions->get($room->id)?->started_at;
            $minutes = $started ? max(1, (int) $started->diffInMinutes(now())) : null;
            $plan = $room->schedules->first()?->minutes() ?? \App\Models\RoomSchedule::DEFAULT_DURATION;

            return [
                'id' => $room->id,
                'title' => $room->name . ($room->user ? ' · ' . $room->user->name : ''),
                'who' => $group
                    ? 'Группа «' . $room->name . '», ' . plural_ru($count, 'ученик', 'ученика', 'учеников')
                    : ($room->participants->first()?->name ?? 'Ученик не добавлен'),
                'duration' => $minutes ? 'идёт ' . $this->span($minutes) : 'идёт сейчас',
                'over' => $minutes !== null && $minutes > $plan + 10,
                'plan' => $plan,
                'joinUrl' => route('rooms.connect', $room->id),
            ];
        });
    }

    /** Учителя для фильтра итогов: [id => имя]. */
    public function teacherOptions(): array
    {
        return User::where('role', User::ROLE_TUTOR)->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Итоги за период (7/30/90 дней, включая сегодня) и данные графика «Занятия по дням».
     *
     * @return array{title:string, stats:array, chart:array}
     */
    public function period(int $days, ?User $teacher = null): array
    {
        $days = in_array($days, self::PERIODS, true) ? $days : 7;
        $from = today()->subDays($days - 1);
        $to = now()->endOfDay();

        $sessions = MeetingSession::query()->whereBetween('started_at', [$from, $to]);
        if ($teacher) {
            $sessions->whereIn('room_id', Room::withTrashed()->where('user_id', $teacher->id)->select('id'));
        }
        $perDay = (clone $sessions)->selectRaw('DATE(started_at) as d, count(*) as n')->groupBy('d')->pluck('n', 'd');
        $total = (int) $perDay->sum();

        $paid = SubscriptionPayment::where('status', SubscriptionPayment::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])
            ->whereNull('meta->card_binding')
            ->when($teacher, fn ($q) => $q->where('user_id', $teacher->id))
            ->sum('amount');

        $lessons = ['v' => number_format($total, 0, ',', ' '), 'label' => 'проведено ' . plural_ru($total, 'занятие', 'занятия', 'занятий', false)];

        if ($teacher) {
            $students = DB::table('room_user')->whereIn('room_id', (clone $sessions)->select('room_id'))->distinct()->count('user_id');
            $stats = [
                $lessons,
                ['v' => (string) $students, 'label' => plural_ru($students, 'ученик занимался', 'ученика занимались', 'учеников занимались', false)],
                $paid > 0
                    ? ['v' => Money::format((int) $paid), 'label' => 'оплата за тариф']
                    : ['v' => 'Нет оплат', 'label' => 'за тариф в этот период'],
            ];
        } else {
            $newTeachers = User::where('role', User::ROLE_TUTOR)->whereBetween('created_at', [$from, $to])->count();
            $stats = [
                $lessons,
                ['v' => (string) $newTeachers, 'label' => plural_ru($newTeachers, 'новый учитель', 'новых учителя', 'новых учителей', false)],
                ['v' => Money::format((int) $paid), 'label' => 'оплат за тарифы'],
            ];
        }

        $word = 'За ' . $days . ' дней';

        return [
            'title' => $teacher ? $word . ': ' . $teacher->name : $word,
            'stats' => $stats,
            'chart' => $this->chart($from, $days, $perDay, $total, $word),
        ];
    }

    /** Столбцы, подписи осей и линия среднего. Высоты — в процентах от верхней границы шкалы. */
    private function chart(Carbon $from, int $days, Collection $perDay, int $total, string $word): array
    {
        $values = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i);
            $values[] = ['date' => $d, 'v' => (int) ($perDay[$d->toDateString()] ?? 0)];
        }

        $top = $this->niceTop(max(array_column($values, 'v') ?: [0]));
        $weekdays = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
        $months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
        $every = match ($days) { 7 => 1, 30 => 7, default => 14 };

        $bars = [];
        foreach ($values as $k => $x) {
            /** @var CarbonInterface $d */
            $d = $x['date'];
            $bars[] = [
                'v' => $x['v'],
                'top' => round(100 - $x['v'] / $top * 100, 2),
                'now' => $k === $days - 1,
                'title' => $weekdays[$d->dayOfWeek] . ', ' . HumanDate::date($d) . ': ' . plural_ru($x['v'], 'занятие', 'занятия', 'занятий'),
                'label' => ($days - 1 - $k) % $every === 0
                    ? ($days === 7 ? $weekdays[$d->dayOfWeek] . ', ' . $d->day : $d->day . ' ' . $months[$d->month - 1])
                    : null,
            ];
        }

        $avg = $total / $days;
        $avgText = $avg >= 10 ? (string) round($avg) : str_replace('.', ',', (string) round($avg, 1));

        return [
            'bars' => $bars,
            'ticks' => [['label' => (string) $top, 'y' => 0], ['label' => (string) ($top / 2), 'y' => 50], ['label' => '0', 'y' => 100]],
            'avgY' => round(100 - $avg / $top * 100, 2),
            'avgText' => 'в среднем ' . $avgText,
            'days' => $days,
            'radius' => match ($days) { 7 => 8, 30 => 4, default => 2 },
            'label' => 'Занятия по дням, ' . mb_strtolower($word) . ': всего ' . $total,
        ];
    }

    /** Верх шкалы: «круглое» чётное число не меньше максимума (подписи 0 · половина · верх). */
    private function niceTop(int $max): int
    {
        if ($max <= 2) {
            return 2;
        }
        $half = $max / 2;
        $pow = 10 ** (int) floor(log10($half));
        foreach ([1, 2, 5, 10] as $m) {
            if ($m * $pow >= $half) {
                return (int) (2 * $m * $pow);
            }
        }

        return (int) (20 * $pow);
    }

    /** «Иван Орлов, Алина Смирнова, Олег Васильев и ещё 2». */
    private function names(Collection $names): string
    {
        $names = $names->filter()->values();

        return $names->take(3)->implode(', ') . ($names->count() > 3 ? ' и ещё ' . ($names->count() - 3) : '');
    }

    /** «20 минут», «3 часа», «2 дня». */
    private function waited(CarbonInterface $since): string
    {
        $minutes = max(1, (int) $since->diffInMinutes(now()));

        return match (true) {
            $minutes < 60 => plural_ru($minutes, 'минуту', 'минуты', 'минут'),
            $minutes < 60 * 24 => plural_ru(intdiv($minutes, 60), 'час', 'часа', 'часов'),
            default => plural_ru(intdiv($minutes, 60 * 24), 'день', 'дня', 'дней'),
        };
    }

    /** «20 минут», «2 часа 10 минут». */
    private function span(int $minutes): string
    {
        if ($minutes < 60) {
            return plural_ru($minutes, 'минуту', 'минуты', 'минут');
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return plural_ru($h, 'час', 'часа', 'часов') . ($m ? ' ' . plural_ru($m, 'минуту', 'минуты', 'минут') : '');
    }

    private function route(string $name, array $params = []): string
    {
        return Route::has('cabinet.admin.' . $name) ? route('cabinet.admin.' . $name, $params) : '#';
    }
}
