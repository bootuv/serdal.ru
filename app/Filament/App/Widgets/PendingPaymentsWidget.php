<?php

namespace App\Filament\App\Widgets;

use App\Models\PaymentRecord;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingPaymentsWidget extends BaseWidget
{
    protected static ?string $heading = 'Ожидают оплаты';

    protected int|string|array $columnSpan = 'full';

    // Между приветствием (-1) и «Ближайшими занятиями» (1)
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        // Секция появляется на дашборде, только когда есть что отмечать
        return request()->routeIs('filament.app.pages.dashboard')
            && PaymentRecord::unpaid()->where('teacher_id', auth()->id())->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PaymentRecord::query()
                    ->unpaid()
                    ->where('teacher_id', auth()->id())
                    ->with(['student', 'meetingSession.room'])
                    ->orderBy('due_date')
            )
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('student.name')
                    ->label('Ученик')
                    ->formatStateUsing(function (string $state, PaymentRecord $record) {
                        $avatarUrl = $record->student?->avatar_url ?? url('/images/default-avatar.png');
                        return new \Illuminate\Support\HtmlString(
                            '<div class="flex items-center gap-3">
                                <img src="' . e($avatarUrl) . '" class="rounded-full object-cover" style="width: 32px; height: 32px;">
                                <span>' . e($state) . '</span>
                            </div>'
                        );
                    }),
                Tables\Columns\TextColumn::make('label')
                    ->label('За что')
                    ->state(fn(PaymentRecord $record): string => $record->label),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Оплата')
                    // Статусы и цвета — как в колонке «Оплата» на странице «Ученики»
                    ->state(function (PaymentRecord $record): string {
                        if ($record->student?->payment_blocked_at) {
                            return 'Заблокирован';
                        }

                        return $record->isOverdue() ? 'Просрочено' : 'Ожидает оплаты';
                    })
                    ->badge()
                    ->icon(fn(string $state): ?string => $state === 'Заблокирован' ? 'heroicon-m-lock-closed' : null)
                    ->color(fn(string $state): string => $state === 'Ожидает оплаты' ? 'warning' : 'danger')
                    ->tooltip(function (PaymentRecord $record, string $state): string {
                        if ($state === 'Заблокирован') {
                            return \App\Filament\App\Resources\StudentResource::blockedPaymentTooltip($record->student);
                        }

                        return $record->isOverdue()
                            ? 'Срок оплаты был ' . $record->due_date->format('d.m.Y')
                            : 'Оплата до ' . $record->due_date->format('d.m.Y');
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_paid')
                    ->label('Отметить оплату')
                    ->icon('heroicon-o-check-circle')
                    ->color('gray')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('Отметить оплату')
                    ->modalDescription(fn(PaymentRecord $record) => "{$record->student?->name} — {$record->label}. Отметить как оплаченное?")
                    ->modalSubmitActionLabel('Оплачено')
                    ->action(function (PaymentRecord $record) {
                        $record->markAs(PaymentRecord::STATUS_PAID, auth()->id());

                        Notification::make()
                            ->title('Отметили: оплачено')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('extend_due')
                        ->label('Продлить срок')
                        ->icon('heroicon-o-clock')
                        ->modalHeading(fn(PaymentRecord $record) => "Продлить срок — {$record->student?->name}")
                        ->modalWidth('md')
                        ->modalSubmitActionLabel('Продлить')
                        ->form([
                            Forms\Components\TextInput::make('extend_days')
                                ->label('На сколько дней продлить')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(60)
                                ->default(3)
                                ->suffix('дн.')
                                ->required(),
                        ])
                        ->action(function (PaymentRecord $record, array $data) {
                            $record->extendDue(max(1, (int) $data['extend_days']));

                            Notification::make()
                                ->title('Срок оплаты продлён')
                                ->body('Новый срок: до ' . $record->fresh()->due_date->format('d.m.Y'))
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('cancel_record')
                        ->label('Не требовать оплату')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Не требовать оплату')
                        ->modalDescription(fn(PaymentRecord $record) => "{$record->student?->name} — {$record->label}. Запись будет отменена, ученик не увидит напоминаний по ней.")
                        ->modalSubmitActionLabel('Не требовать')
                        ->action(function (PaymentRecord $record) {
                            $record->markAs(PaymentRecord::STATUS_CANCELLED, auth()->id());

                            Notification::make()
                                ->title('Готово: оплата не требуется')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('payment_settings')
                        ->label('Настройки оплаты')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->modalHeading(fn(PaymentRecord $record) => "Настройки оплаты — {$record->student?->name}")
                        ->modalWidth('md')
                        ->modalSubmitActionLabel('Сохранить')
                        ->form(fn(PaymentRecord $record) => \App\Filament\App\Resources\StudentResource::getPaymentSettingsFormSchema($record->student))
                        ->action(fn(PaymentRecord $record, array $data) => \App\Filament\App\Resources\StudentResource::applyPaymentSettings($record->student, $data)),
                ])
                    ->color('gray'),
            ]);
    }
}
