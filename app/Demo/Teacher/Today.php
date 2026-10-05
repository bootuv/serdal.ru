<?php

namespace App\Demo\Teacher;

use App\Demo\Concerns\TeacherModals;
use App\Demo\Screen;
use App\Demo\World;
use App\Livewire\Cabinet\Teacher\Concerns\LessonRows;
use App\Support\HumanDate;
use Carbon\Carbon;
use Illuminate\Support\Str;

/** «Сегодня» учителя — App\Livewire\Cabinet\Teacher\Today. */
class Today extends Screen
{
    use LessonRows, TeacherModals;

    public const PATH = '';

    /** Состояния для DemoCabinetTest. */
    public const EXAMPLES = ['', '?open=plan', '?open=plan&planKind=group&planStudents=106,107', '?mark=103'];

    public string $view = 'livewire.cabinet.teacher.today';

    public string $title = 'Сегодня';

    public ?string $active = 'today';

    public function actions(): array
    {
        return $this->modalActions() + \App\Demo\NewsBanner::actions() + [
            'undoPaid' => ['toast' => 'Отметка об оплате отменена'],
        ];
    }

    public function components(): array
    {
        return ['cabinet.news-banner' => \App\Demo\NewsBanner::entry()];
    }

    public function data(): array
    {
        $teacher = World::teacher();

        return [
            'firstName' => $teacher->first_name,
            'today' => HumanDate::todayLong(),
            'empty' => false,
            'tariff' => self::tariff(),
            'limitBanner' => null,
            'inviteUrl' => route('cabinet.teacher.students', ['invite' => 1]),
            'scheduleUrl' => route('cabinet.teacher.schedule'),
        ] + $this->lessonsToday() + $this->review() + $this->payments() + $this->messages() + $this->modalData();
    }

    /** Карточка тарифа (SubscriptionService::teacherSummary). */
    public static function tariff(): array
    {
        return [
            'name' => 'Профи',
            'url' => route('cabinet.teacher.subscription'),
            'until' => 'до ' . HumanDate::date(Carbon::now()->addDays(23)),
            'limit' => 120,
            'used' => 64,
            'left' => 56,
            'extra' => 0,
            'resets' => HumanDate::date(Carbon::now()->addDays(23)),
            'limits' => ['до 12 участников в занятии', 'записи хранятся 90 дней'],
            'warning' => null,
        ];
    }

    private function lessonsToday(): array
    {
        $lessons = World::lessons(Carbon::today(), Carbon::today()->endOfDay());
        $focus = $lessons->first(fn ($l) => ! $l['past']);

        $rows = $lessons->map(function (array $l) use ($focus) {
            if ($focus && $l['key'] === $focus['key']) {
                return $this->lessonRow($l, [
                    'focus' => true,
                    'status' => Str::ucfirst('начнётся ' . (HumanDate::until($l['start']) ?? 'сейчас')),
                    'facts' => mb_strtolower($this->kindFacts($l)),
                    'action' => ['kind' => 'start', 'url' => route('cabinet.teacher.lesson', ['room' => $l['roomId'], 'class' => 1])],
                ]);
            }

            if ($l['past']) {
                return $this->lessonRow($l, ['facts' => 'Завершено · запись готова']);
            }

            return $this->lessonRow($l, ['facts' => $this->kindFacts($l)]);
        });

        return ['rows' => $rows->values(), 'focusKey' => $focus['key'] ?? null, 'nextLesson' => null];
    }

    private function review(): array
    {
        $now = Carbon::now();
        $items = collect([
            [401, 'Квадратные уравнения, вариант 3', 101, $now->copy()->subHours(3), 0],
            [402, 'Производная: задачи 1–12', 102, $now->copy()->subDay()->setTime(21, 15), 1],
            [403, 'Законы Ньютона, задачи 1–8', 103, $now->copy()->subDays(3)->setTime(18, 2), 3],
        ])->map(fn (array $r) => [
            'key' => $r[0],
            'title' => $r[1],
            'student' => World::student($r[2])->name,
            'studentId' => $r[2],
            'studentPhoto' => null,
            'submitted' => 'сдано ' . (in_array(HumanDate::day($r[3]), ['сегодня', 'вчера'], true) ? HumanDate::day($r[3]) . ' в ' . $r[3]->format('H:i') : HumanDate::day($r[3])),
            'waits' => $r[4] >= 2 ? 'ждёт ' . plural_ru($r[4], 'день', 'дня', 'дней') : null,
            'overdue' => $r[4] >= 3,
            'url' => route('cabinet.teacher.review', ['submission' => $r[0]]),
        ]);

        return ['reviewCount' => 3, 'review' => $items, 'reviewAllUrl' => route('cabinet.teacher.tasks')];
    }

    private function payments(): array
    {
        $rows = collect([
            ['id' => 103, 'facts' => '2 занятия · 3 000 ₽', 'badge' => 'Просрочено', 'overdue' => true],
            ['id' => 104, 'facts' => '1 занятие · 1 500 ₽', 'badge' => null, 'overdue' => false],
        ])->map(fn (array $p) => $p + [
            'name' => World::student($p['id'])->name,
            'photo' => null,
            'paid' => false,
            'claim' => null,
        ]);

        return ['payments' => $rows, 'paymentsUrl' => route('cabinet.teacher.students')];
    }

    private function messages(): array
    {
        $now = Carbon::now();
        $messages = collect([
            [501, 101, 'Отправил домашку, в пятом не уверен — посмотрите?', $now->copy()->subMinutes(40), 1],
            [502, 102, 'Можно перенести четверг на 18:00?', $now->copy()->subHours(2), 1],
            [503, 107, 'Спасибо за разбор пробника!', $now->copy()->subDay(), 0],
        ])->map(fn (array $m) => [
            'key' => $m[0],
            'name' => World::student($m[1])->name,
            'userId' => $m[1],
            'photo' => null,
            'time' => $m[3]->isToday() ? $m[3]->format('H:i') : HumanDate::day($m[3]),
            'text' => $m[2],
            'unread' => $m[4],
            'url' => route('cabinet.teacher.messages', ['chat' => $m[1]]),
        ]);

        return ['messages' => $messages, 'messagesUnread' => 2, 'messagesUrl' => route('cabinet.teacher.messages')];
    }
}
