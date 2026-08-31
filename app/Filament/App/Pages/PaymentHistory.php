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
                    ->with('tariff')
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tariff.name')
                    ->label('Тариф'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Сумма')
                    ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' ₽'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        SubscriptionPayment::STATUS_PAID => 'Оплачен',
                        SubscriptionPayment::STATUS_PENDING => 'Ожидает оплаты',
                        SubscriptionPayment::STATUS_REFUNDED => 'Возврат',
                        default => 'Не прошёл',
                    })
                    ->color(fn(string $state) => match ($state) {
                        SubscriptionPayment::STATUS_PAID => 'success',
                        SubscriptionPayment::STATUS_PENDING => 'warning',
                        SubscriptionPayment::STATUS_REFUNDED => 'gray',
                        default => 'danger',
                    }),
            ])
            ->actions([
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
