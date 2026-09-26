<?php

namespace App\Notifications;

use App\Models\PaymentClaim;
use App\Notifications\Traits\BroadcastsNotification;
use App\Services\TeacherStudentsService;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

/** Учителю: ученик сообщил об оплате занятий (с чеком). Ссылка — карточка ученика, вкладка «Оплата». */
class PaymentClaimSubmitted extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    // Важное уведомление: играть звук в кабинете при получении
    public bool $broadcastSound = true;

    public function __construct(public PaymentClaim $claim)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = \NotificationChannels\WebPush\WebPushChannel::class;
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        $claim = $this->claim;
        $student = $claim->student;
        $count = $claim->records()->count();

        $body = ($student?->name ?? 'Ученик') . ' сообщил(а) об оплате: '
            . plural_ru($count, 'занятие', 'занятия', 'занятий')
            . ($claim->amount ? ' · ' . \App\Support\Money::format($claim->amount) : '')
            . '. Проверьте чек и подтвердите оплату.';

        return FilamentNotification::make()
            ->title('Ученик сообщил об оплате')
            ->body($body)
            ->icon('heroicon-o-banknotes')
            ->iconColor('warning')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Проверить')
                    ->button()
                    ->url($student ? TeacherStudentsService::studentUrl($student, ['tab' => 'pay']) : url('/tutor/students')),
            ])
            ->getDatabaseMessage();
    }
}
