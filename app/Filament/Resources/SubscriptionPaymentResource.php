<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionPaymentResource\Pages;
use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Services\SubscriptionService;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionPaymentResource extends Resource
{
    protected static ?string $model = SubscriptionPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Платежи подписок';

    protected static ?string $modelLabel = 'Платёж';

    protected static ?string $pluralModelLabel = 'Платежи подписок';

    protected static ?string $slug = 'subscription-payments';

    protected static ?int $navigationSort = 9;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Преподаватель')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tariff.name')
                    ->label('Тариф'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Сумма')
                    ->money('rub'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn(SubscriptionPayment $record) => $record->status_label)
                    ->color(fn(string $state) => match ($state) {
                        SubscriptionPayment::STATUS_PAID => 'success',
                        SubscriptionPayment::STATUS_PENDING => 'warning',
                        SubscriptionPayment::STATUS_FAILED => 'danger',
                        SubscriptionPayment::STATUS_REFUNDED => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('gateway_order_id')
                    ->label('ID платежа в ЮKassa')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Оплачен')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        SubscriptionPayment::STATUS_PENDING => 'Ожидает оплаты',
                        SubscriptionPayment::STATUS_PAID => 'Оплачен',
                        SubscriptionPayment::STATUS_FAILED => 'Не прошёл',
                        SubscriptionPayment::STATUS_REFUNDED => 'Возврат',
                    ]),
                Tables\Filters\SelectFilter::make('tariff_id')
                    ->label('Тариф')
                    ->options(Tariff::orderBy('sort')->pluck('name', 'id')),
            ])
            ->actions([
                // Ручное подтверждение — на случай, если оплата прошла, а уведомление ЮKassa не дошло
                Tables\Actions\Action::make('markPaid')
                    ->label('Подтвердить оплату')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->modalDescription('Подписка будет активирована/продлена, как при успешной оплате. Используйте, только если оплата подтверждена в личном кабинете ЮKassa.')
                    ->visible(fn(SubscriptionPayment $record) => $record->status === SubscriptionPayment::STATUS_PENDING)
                    ->action(function (SubscriptionPayment $record) {
                        SubscriptionService::applyPaidPayment($record);
                        Notification::make()->title('Платёж подтверждён, подписка активирована')->success()->send();
                    }),
                Tables\Actions\Action::make('refund')
                    ->label('Оформить возврат')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Возврат платежа')
                    ->modalDescription(fn(SubscriptionPayment $record) => $record->gateway_order_id
                        ? 'Деньги (' . number_format($record->amount, 0, ',', ' ') . ' ₽) вернутся на карту плательщика через ЮKassa. Действие необратимо. Срок подписки автоматически уменьшится на оплаченный этим платежом период.'
                        : 'У платежа нет идентификатора ЮKassa (создан вручную) — он будет отмечен как возвращённый без движения денег, срок подписки уменьшится на оплаченный период.')
                    ->visible(fn(SubscriptionPayment $record) => $record->status === SubscriptionPayment::STATUS_PAID)
                    ->action(function (SubscriptionPayment $record) {
                        // Платёж без id шлюза — только отметка в учёте
                        if (!$record->gateway_order_id) {
                            $record->update([
                                'status' => SubscriptionPayment::STATUS_REFUNDED,
                                'meta' => array_merge($record->meta ?? [], ['refunded_at' => now()->toIso8601String()]),
                            ]);
                            $adjusted = \App\Services\SubscriptionService::applyRefund($record);
                            $record->user?->notify(new \App\Notifications\SubscriptionRefunded(
                                $record->tariff->name,
                                $record->amount,
                                \App\Support\OfferSettings::offer()['refund_processing_days'],
                                newEndsAt: $adjusted?->isActive() ? $adjusted->ends_at : null,
                                subscriptionEnded: $adjusted !== null && !$adjusted->isActive(),
                            ));
                            Notification::make()->title('Платёж отмечен как возвращённый')->success()->send();
                            return;
                        }

                        if (\App\Services\YooKassaService::refundPayment($record)) {
                            $record->update(['status' => SubscriptionPayment::STATUS_REFUNDED]);

                            // Привязочные платежи — служебные: подписку не трогаем, учителя не уведомляем
                            $adjusted = null;
                            if (empty($record->meta['card_binding'])) {
                                $adjusted = \App\Services\SubscriptionService::applyRefund($record);
                                $record->user?->notify(new \App\Notifications\SubscriptionRefunded(
                                    $record->tariff->name,
                                    $record->amount,
                                    \App\Support\OfferSettings::offer()['refund_processing_days'],
                                    newEndsAt: $adjusted?->isActive() ? $adjusted->ends_at : null,
                                    subscriptionEnded: $adjusted !== null && !$adjusted->isActive(),
                                ));
                            }

                            $subscriptionNote = match (true) {
                                $adjusted === null => '',
                                !$adjusted->isActive() => ' Подписка завершена.',
                                default => ' Подписка сокращена до ' . $adjusted->ends_at->format('d.m.Y') . '.',
                            };

                            Notification::make()
                                ->title('Возврат оформлен')
                                ->body('Деньги вернутся на карту плательщика в течение нескольких дней. Учителю отправлено уведомление.' . $subscriptionNote)
                                ->success()
                                ->send();
                        } else {
                            $error = $record->fresh()->meta['refund_response']['description'] ?? 'Проверьте баланс магазина и статус платежа в личном кабинете ЮKassa.';
                            Notification::make()
                                ->title('Не удалось оформить возврат')
                                ->body($error)
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'tariff']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionPayments::route('/'),
        ];
    }
}
