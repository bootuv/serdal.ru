<?php

namespace App\Filament\Student\Resources;

use App\Filament\Student\Resources\HomeworkResource\Pages;
use App\Models\Homework;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Colors\Color;

class HomeworkResource extends Resource
{
    protected static ?string $model = Homework::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Домашние задания';

    protected static ?string $modelLabel = 'Домашнее задание';

    protected static ?string $pluralModelLabel = 'Домашние задания';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('type_label')
                    ->label('Тип')
                    ->badge()
                    ->color(fn(Homework $record): string => $record->type_color)
                    ->icon(fn(Homework $record): string => $record->type_icon),

                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Учитель')
                    ->sortable(),

                Tables\Columns\TextColumn::make('room.name')
                    ->label('Урок')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('deadline')
                    ->label('Срок сдачи')
                    ->formatStateUsing(fn($state) => format_datetime($state))
                    ->sortable()
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
                        if ($submission->grade !== null) {
                            return 'Оценено';
                        }
                        return 'На проверке';
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Не сдано' => 'gray',
                        'На проверке' => 'warning',
                        'На доработке' => 'danger',
                        'Оценено' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('grade')
                    ->label('Оценка')
                    ->getStateUsing(function (Homework $record) {
                        $submission = $record->submissions()
                            ->where('student_id', auth()->id())
                            ->first();
                        return $submission?->grade ?? '—';
                    })
                    ->badge()
                    ->color(fn($state) => $state !== '—' ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'pending' => 'Не сдано',
                        'submitted' => 'На проверке',
                        'revision_requested' => 'На доработке',
                        'graded' => 'Оценено',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!$data['value']) {
                            return $query;
                        }

                        $studentId = auth()->id();

                        return match ($data['value']) {
                            'pending' => $query->whereDoesntHave('submissions', function ($q) use ($studentId) {
                                    $q->where('student_id', $studentId)->whereNotNull('submitted_at');
                                }),
                            'submitted' => $query->whereHas('submissions', function ($q) use ($studentId) {
                                    $q->where('student_id', $studentId)
                                    ->where('status', \App\Models\HomeworkSubmission::STATUS_SUBMITTED);
                                }),
                            'revision_requested' => $query->whereHas('submissions', function ($q) use ($studentId) {
                                    $q->where('student_id', $studentId)
                                    ->where('status', \App\Models\HomeworkSubmission::STATUS_REVISION_REQUESTED);
                                }),
                            'graded' => $query->whereHas('submissions', function ($q) use ($studentId) {
                                    $q->where('student_id', $studentId)->whereNotNull('grade');
                                }),
                            default => $query,
                        };
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                //
            ])
            ->emptyStateHeading('Нет домашних заданий')
            ->emptyStateDescription('Когда учитель назначит вам домашнее задание, оно появится здесь')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->whereDoesntHave('submissions', function ($query) {
                $query->where('student_id', auth()->id())
                    ->whereNotNull('grade');
            })
            ->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHomeworks::route('/'),
            'view' => Pages\ViewHomework::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_visible', true)
            ->whereHas('students', function ($query) {
                $query->where('users.id', auth()->id());
            });
    }
}
