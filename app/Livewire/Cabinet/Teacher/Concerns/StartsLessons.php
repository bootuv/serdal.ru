<?php

namespace App\Livewire\Cabinet\Teacher\Concerns;

use App\Models\Room;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Route;

/**
 * «Начать занятие» в кабинете учителя: ссылка на запуск (rooms.start) или окно «Занятие недоступно»,
 * если подписка не позволяет начать новое занятие (логика — SubscriptionService, как LessonStartModal в старом кабинете).
 * Окно: partials/start-blocked.blade.php, свойство $startBlockedOpen.
 */
trait StartsLessons
{
    public bool $startBlockedOpen = false;

    /** Причина, по которой нельзя начать новое занятие (null — можно). */
    protected function startBlockReason(User $teacher): ?string
    {
        return SubscriptionService::canStartLesson($teacher);
    }

    /** Идёт ли у учителя другое занятие (тогда новое начать нельзя — как в RoomController::start). */
    protected function otherRunningRoomId(User $teacher, ?int $exceptRoomId = null): ?int
    {
        return Room::where('user_id', $teacher->id)
            ->where('is_running', true)
            ->when($exceptRoomId, fn ($q) => $q->where('id', '!=', $exceptRoomId))
            ->value('id');
    }

    /**
     * Данные окна и плашки «Занятия по тарифу закончились» / «Подписка закончилась». Null — ограничений нет.
     *
     * @return array{title:string, text:string, hint:?string, limit:bool, primary:array{label:string, url:string}, compareUrl:string, referral:?array}|null
     */
    protected function startBlock(User $teacher): ?array
    {
        $reason = $this->startBlockReason($teacher);

        if ($reason === null) {
            return null;
        }

        $limit = SubscriptionService::lessonLimitReached($teacher);
        $subscriptionUrl = Route::has('cabinet.teacher.subscription') ? route('cabinet.teacher.subscription') : url('/tutor/subscription');
        $canBuy = $limit && SubscriptionService::canBuyExtraLessons($teacher);

        return [
            'title' => $limit ? 'Занятия по тарифу закончились' : 'Подписка закончилась',
            'text' => $reason,
            'hint' => $limit ? 'В лимит идут занятия дольше 5 минут, где был хотя бы один ученик.' : null,
            'limit' => $limit,
            'primary' => $canBuy
                ? ['label' => 'Докупить занятия', 'url' => $subscriptionUrl . '?buy=1']
                : ['label' => 'Выбрать тариф', 'url' => $subscriptionUrl],
            'compareUrl' => $subscriptionUrl,
            'referral' => $limit && ReferralService::enabled()
                ? [
                    'label' => '+' . plural_ru(ReferralService::referrerBonus(), 'занятие', 'занятия', 'занятий') . ' за приглашение коллеги',
                    'url' => Route::has('cabinet.teacher.referrals') ? route('cabinet.teacher.referrals') : url('/tutor/referrals'),
                ]
                : null,
        ];
    }

    public function closeStartBlocked(): void
    {
        $this->startBlockedOpen = false;
    }
}
