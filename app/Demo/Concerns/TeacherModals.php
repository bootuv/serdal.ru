<?php

namespace App\Demo\Concerns;

use App\Demo\World;
use App\Services\TeacherLessonService;
use App\Support\HumanDate;
use App\Support\Money;
use Carbon\Carbon;

/**
 * Окна, общие для экранов учителя (Сегодня, Расписание, Ученики, Ученик) — как трейты настоящих
 * компонентов: PlansLessons (lesson-plan-modal), StartsLessons (lesson-start-blocked),
 * MarksPayments (lesson-mark-paid-modal), ReviewsPaymentClaims (payment-claim-modal).
 *
 * Состояние — в адресе: ?open=plan&planKind=group&planDays=1,3 …, ?mark=101&markSelected=…
 */
trait TeacherModals
{
    public function modalParams(): array
    {
        return ['open', 'planKind', 'planStudentId', 'planStudents', 'planRepeat', 'planDays', 'planDuration', 'mark', 'markSelected'];
    }

    protected function modalActions(): array
    {
        return [
            'openPlan' => ['set' => ['open' => 'plan', 'planStudentId' => '{0}']],
            'closePlan' => ['close' => true],
            'savePlan' => ['close' => true, 'toast' => 'Занятие запланировано, ученик получит уведомление'],
            'togglePlanDay' => ['toggle' => 'planDays'],
            'addPlanStudent' => ['add' => 'planStudents'],
            'removePlanStudent' => ['remove' => 'planStudents'],
            'markPaid' => ['set' => ['mark' => '{0}']],
            'confirmMarkPaid' => ['close' => true, 'toast' => 'Оплата отмечена'],
            'remind' => ['toast' => 'Напоминание отправлено'],
        ];
    }

    /** Публичные свойства трейтов и данные открытых окон. */
    protected function modalData(): array
    {
        $now = Carbon::now();
        $planOpen = $this->state('open') === 'plan';
        $planKind = $this->state('planKind', 'individual');
        $planRepeat = $this->state('planRepeat', 'weekly');
        $planDuration = $this->state('planDuration', 60);
        $planDays = $this->stateInts('planDays', [$now->copy()->addDay()->dayOfWeek]);
        $planStudents = $this->stateInts('planStudents');
        $planStudentId = (string) $this->state('planStudentId', '');
        $markId = $this->state('mark', 0);

        $people = World::students()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'email' => $s->email, 'photo' => null])
            ->sortBy('name')->values();
        $first = $now->copy()->addDay()->setTime(16, 0);

        $data = [
            'planOpen' => $planOpen,
            'planKind' => $planKind,
            'planStudentId' => $planStudentId,
            'planStudents' => $planStudents,
            'planAdd' => '',
            'planName' => '',
            'planDate' => $first->format('Y-m-d'),
            'planTime' => '16:00',
            'planDuration' => $planDuration,
            'planRepeat' => $planRepeat,
            'planDays' => $planDays,
            'planUntil' => '',
            'planSlots' => [],
            'startBlockedOpen' => false,
            'startBlock' => null,
            'justPaid' => [],
            'markStudentId' => $markId ?: null,
            'markSelected' => [],
            'claimId' => null,
            'claimRejecting' => false,
            'claimReason' => '',
            'claimView' => null,
        ];

        if ($planOpen) {
            $data += [
                'planPeople' => $people->all(),
                'planGroupPeople' => $people->reject(fn ($p) => in_array($p['id'], $planStudents, true))->values()->all(),
                'planChosen' => collect($planStudents)->map(fn ($id) => ['id' => $id, 'name' => World::student($id)->name])->all(),
                'planWhoHint' => $planKind === 'group' && $planStudents
                    ? plural_ru(count($planStudents), 'ученик', 'ученика', 'учеников') . ' · на «Профи» до 12 в занятии'
                    : 'Нового ученика сначала пригласите в разделе «Ученики»',
                'planPrice' => $planKind === 'group'
                    ? ['main' => Money::format(5000) . ' в месяц с каждого ученика · оплата до 5 числа', 'sub' => 'Начисляем каждому ученику отдельно.', 'link' => 'Изменить цены']
                    : ['main' => Money::format(1500) . ' за занятие · оплата в течение 3 дней', 'sub' => 'Начисляем, только если ученик был на занятии.', 'link' => 'Изменить цены'],
                'planPricesUrl' => route('cabinet.teacher.profile', ['tab' => 'prices']),
                'planFirst' => ($planRepeat === 'weekly' ? 'Первое занятие — ' : 'Разовое занятие — ') . HumanDate::at($first),
                'planDurations' => TeacherLessonService::durationOptions($planDuration),
            ];
        }

        if ($markId && isset(World::STUDENTS[$markId])) {
            $price = World::ROOMS[World::roomOf($markId)][4] ?? 1500;
            $rows = collect([3, 6])->map(fn (int $daysAgo, int $i) => [
                'id' => $markId * 10 + $i,
                'title' => 'Занятие ' . HumanDate::date($now->copy()->subDays($daysAgo)),
                'hint' => ($daysAgo > 4 ? 'срок был до ' : 'оплатить до ') . HumanDate::date($now->copy()->subDays($daysAgo)->addDays(3)),
                'overdue' => $daysAgo > 4,
                'amount' => $price,
            ]);
            $selected = $this->stateInts('markSelected', $rows->pluck('id')->all());
            $data['markStudent'] = World::student($markId);
            $data['markSelected'] = $selected;
            $data['markRows'] = $rows->all();
            $data['markSum'] = $rows->whereIn('id', $selected)->sum('amount');
        }

        return $data;
    }
}
