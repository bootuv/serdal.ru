<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\PaymentRecord;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Начисления ученика в суперадминке: у кого, за что, срок и статус.
 * Бейдж вкладки — число преподавателей, к чьим занятиям ученик сейчас не допускается
 * (см. PaymentRecordService::debtStatus). Только просмотр: отмечает оплату преподаватель.
 */
class PaymentRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentRecords';

    protected static ?string $title = 'Оплата занятий';
    protected static ?string $icon = 'heroicon-o-banknotes';
    protected static ?string $modelLabel = 'Начисление';
    protected static ?string $pluralModelLabel = 'Начисления';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof User && $ownerRecord->role === User::ROLE_STUDENT;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $blocked = count(\App\Services\PaymentRecordService::blockedTeacherIds($ownerRecord->id));

        return $blocked > 0 ? (string) $blocked : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'danger';
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with(['teacher:id,name', 'meetingSession.room']))
            ->defaultSort('due_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Преподаватель')
                    ->placeholder('Удалён')
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->label('За что')
                    ->state(fn(PaymentRecord $record): string => $record->label),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Срок оплаты')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->state(fn(PaymentRecord $record): string => static::statusLabel($record))
                    ->color(fn(string $state): string => match ($state) {
                        'Оплачено' => 'success',
                        'Оплата не требуется' => 'gray',
                        'Ожидает оплаты' => 'warning',
                        default => 'danger',
                    })
                    ->icon(fn(string $state): ?string => $state === 'Блокирует занятия' ? 'heroicon-m-lock-closed' : null)
                    ->tooltip(fn(PaymentRecord $record, string $state): ?string => $state === 'Блокирует занятия'
                        ? static::blockTooltip($record)
                        : null),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Отмечено')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('overdue')
                    ->label('Только просроченные')
                    ->toggle()
                    ->query(fn($query) => $query->overdue()),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('Начислений нет')
            ->emptyStateDescription('Записи появляются после проведённых занятий (поурочная оплата) или в начале месяца (помесячная).');
    }

    public static function statusLabel(PaymentRecord $record): string
    {
        return match (true) {
            $record->status === PaymentRecord::STATUS_PAID => 'Оплачено',
            $record->status === PaymentRecord::STATUS_CANCELLED => 'Оплата не требуется',
            // Просроченная запись у преподавателя, к чьим занятиям ученик уже не допускается
            $record->isOverdue() && \App\Services\PaymentRecordService::isBlockedForTeacher((int) $record->student_id, (int) $record->teacher_id) => 'Блокирует занятия',
            $record->isOverdue() => 'Просрочено',
            default => 'Ожидает оплаты',
        };
    }

    protected static function blockTooltip(PaymentRecord $record): string
    {
        $status = \App\Services\PaymentRecordService::debtStatus((int) $record->student_id, (int) $record->teacher_id);

        return 'После срока оплаты ' . $status['debt_since']?->format('d.m.Y') . ' ученик посетил '
            . trans_choice('{1} :count занятие|[2,4] :count занятия|[5,*] :count занятий', $status['lessons_with_debt'])
            . ' этого преподавателя и больше не допускается к его занятиям, пока тот не отметит оплату или не продлит срок.';
    }
}
