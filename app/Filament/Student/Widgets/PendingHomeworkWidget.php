<?php

namespace App\Filament\Student\Widgets;

use App\Models\Homework;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingHomeworkWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        $studentId = auth()->id();

        return $table
            ->query(
                Homework::query()
                    ->whereHas('students', function (Builder $query) use ($studentId) {
                        $query->where('users.id', $studentId);
                    })
                    ->where(function (Builder $query) use ($studentId) {
                        // No submission at all, or submitted but not graded
                        $query->whereDoesntHave('submissions', function ($q) use ($studentId) {
                            $q->where('student_id', $studentId)->whereNotNull('grade');
                        });
                    })
                    ->orderBy('deadline', 'asc')
                    ->limit(5)
            )
            ->heading('Домашние задания')
            ->headerActions([
                Tables\Actions\Action::make('viewAll')
                    ->label('Все задания')
                    ->url(\App\Filament\Student\Resources\HomeworkResource::getUrl('index'))
                    ->link(),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Название')
                    ->limit(30)
                    ->tooltip(fn(Homework $record) => $record->title),

                Tables\Columns\TextColumn::make('type_label')
                    ->label('Тип')
                    ->badge()
                    ->color(fn(Homework $record): string => $record->type_color),

                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Учитель')
                    ->formatStateUsing(function ($state) {
                        $parts = explode(' ', trim($state));
                        if (count($parts) >= 3) {
                            return mb_substr($parts[1], 0, 1) . '.' . mb_substr($parts[2], 0, 1) . '. ' . $parts[0];
                        } elseif (count($parts) === 2) {
                            return mb_substr($parts[1], 0, 1) . '. ' . $parts[0];
                        }
                        return $state;
                    }),

                Tables\Columns\TextColumn::make('deadline')
                    ->label('Срок сдачи')
                    ->formatStateUsing(fn($state) => format_datetime($state))
                    ->color(fn(Homework $record) => $record->is_overdue ? 'danger' : null),

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->getStateUsing(function (Homework $record) {
                        $submission = $record->submissions()
                            ->where('student_id', auth()->id())
                            ->first();

                        if (!$submission || !$submission->submitted_at) {
                            return 'Не сдано';
                        }
                        if ($submission->status === \App\Models\HomeworkSubmission::STATUS_REVISION_REQUESTED) {
                            return 'На доработке';
                        }
                        return 'На проверке';
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Не сдано' => 'gray',
                        'На проверке' => 'warning',
                        'На доработке' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->emptyStateHeading('Нет активных заданий')
            ->emptyStateDescription('')
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->recordUrl(fn(Homework $record) => \App\Filament\Student\Resources\HomeworkResource::getUrl('view', ['record' => $record]))
            ->paginated(false);
    }
}
