<?php

namespace App\Filament\App\Support;

use App\Filament\App\Pages\ManageSubscription;
use App\Services\SubscriptionService;
use Filament\Actions\MountableAction;

/**
 * Модалка «Занятие недоступно» для кнопок «Начать занятие». Показывается,
 * когда подписка не позволяет запустить новое занятие. Если причина — исчерпан
 * лимит, предлагает докупить занятия (основная кнопка) или перейти на тариф
 * выше; если срок подписки истёк — ведёт к выбору тарифа.
 */
class LessonStartModal
{
    public static function apply(MountableAction $action): MountableAction
    {
        return $action
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-credit-card')
            ->modalIconColor('warning')
            ->modalHeading('Занятие недоступно')
            ->modalDescription(fn() => SubscriptionService::canStartLesson(auth()->user()))
            ->modalSubmitActionLabel(fn() => self::limitReached() ? 'Докупить занятия' : 'Перейти к подписке')
            ->modalCancelActionLabel('Закрыть')
            ->extraModalFooterActions(fn(MountableAction $action) => self::limitReached()
                ? [
                    $action->makeModalAction('upgrade')
                        ->label('Тариф выше')
                        ->color('gray')
                        ->url(ManageSubscription::getUrl()),
                ]
                : [])
            ->action(fn($livewire) => $livewire->redirect(self::limitReached()
                ? ManageSubscription::getUrl(['buy' => 1])
                : ManageSubscription::getUrl()));
    }

    /**
     * Блокировка вызвана исчерпанным лимитом занятий (а не истёкшей подпиской).
     */
    protected static function limitReached(): bool
    {
        return SubscriptionService::lessonLimitReached(auth()->user());
    }
}
