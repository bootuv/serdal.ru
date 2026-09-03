<?php

namespace App\Filament\App\Pages;

use App\Models\SubscriptionPayment;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class PaymentHistory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Платежи';

    protected static ?string $title = 'История платежей';

    protected static ?string $slug = 'payments';

    protected static string $view = 'filament.app.pages.payment-history';

    /**
     * Страница доступна из выпадающего меню профиля, в сайдбаре не показывается.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SubscriptionPayment::query()
                    ->where('user_id', auth()->id())
                    // Неудавшиеся попытки учителю не показываем (для сверки они остаются в админке)
                    ->where('status', '!=', SubscriptionPayment::STATUS_FAILED)
                    ->with('tariff')
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tariff.name')
                    ->label('Тариф')
                    ->formatStateUsing(fn($state, SubscriptionPayment $record) => !empty($record->meta['card_binding']) ? 'Привязка карты' : $state),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Сумма')
                    ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' ₽'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        SubscriptionPayment::STATUS_PAID => 'Оплачен',
                        SubscriptionPayment::STATUS_PENDING => 'Ожидает оплаты',
                        SubscriptionPayment::STATUS_REFUNDED => 'Возврат оформлен',
                        default => 'Не прошёл',
                    })
                    ->color(fn(string $state) => match ($state) {
                        SubscriptionPayment::STATUS_PAID => 'success',
                        SubscriptionPayment::STATUS_PENDING => 'warning',
                        SubscriptionPayment::STATUS_REFUNDED => 'info',
                        default => 'danger',
                    })
                    ->tooltip(function (SubscriptionPayment $record) {
                        if ($record->status !== SubscriptionPayment::STATUS_REFUNDED) {
                            return null;
                        }

                        if (!empty($record->meta['card_binding'])) {
                            return 'Проверочный 1 ₽ возвращён на карту.';
                        }

                        $date = !empty($record->meta['refunded_at'])
                            ? \Illuminate\Support\Carbon::parse($record->meta['refunded_at'])->format('d.m.Y')
                            : $record->updated_at->format('d.m.Y');
                        $days = \App\Support\OfferSettings::offer()['refund_processing_days'];

                        return "Возврат оформлен {$date}. Средства вернутся на карту, с которой была оплата, в течение {$days} рабочих дней.";
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('resume')
                    ->label(fn(SubscriptionPayment $record) => 'Оплатить · до ' . $record->created_at->copy()->addHour()->format('H:i'))
                    ->icon('heroicon-o-credit-card')
                    ->color('warning')
                    ->url(fn(SubscriptionPayment $record) => $record->payment_url)
                    ->visible(fn(SubscriptionPayment $record) => $record->isResumable()),
                Tables\Actions\Action::make('receipt')
                    ->label('Квитанция')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn(SubscriptionPayment $record) => route('subscription.payment.receipt', $record))
                    ->openUrlInNewTab()
                    ->visible(fn(SubscriptionPayment $record) => $record->status === SubscriptionPayment::STATUS_PAID),
            ])
            ->emptyStateHeading('Платежей пока нет')
            ->emptyStateDescription('Здесь появится история оплат подписки.')
            ->paginated([10, 25, 50]);
    }
}
