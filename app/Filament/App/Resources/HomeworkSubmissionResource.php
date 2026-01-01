<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\HomeworkSubmissionResource\Pages;
use App\Models\HomeworkSubmission;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HomeworkSubmissionResource extends Resource
{
    protected static ?string $model = HomeworkSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Проверка работ';

    protected static ?string $modelLabel = 'Работа';

    protected static ?string $pluralModelLabel = 'Проверка работ';

    protected static ?int $navigationSort = 6;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.name')
                    ->label('Ученик')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('homework.title')
                    ->label('Задание')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Сдано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status_label')
                    ->label('Статус')
                    ->badge()
                    ->color(fn(HomeworkSubmission $record): string => match ($record->status_label) {
                        'Не сдано' => 'gray',
                        'На проверке' => 'warning',
                        'Оценено' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('grade')
                    ->label('Оценка')
                    ->placeholder('—')
                    ->badge()
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'pending' => 'На проверке',
                        'graded' => 'Оценено',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!$data['value']) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'pending' => $query->whereNotNull('submitted_at')->whereNull('grade'),
                            'graded' => $query->whereNotNull('grade'),
                            default => $query,
                        };
                    }),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Проверить'),
            ])
            ->emptyStateHeading('Нет работ на проверку')
            ->emptyStateDescription('Когда ученики сдадут домашние задания, они появятся здесь')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHomeworkSubmissions::route('/'),
            'view' => Pages\ViewHomeworkSubmission::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotNull('submitted_at')
            ->whereHas('homework', function ($query) {
                $query->where('teacher_id', auth()->id());
            });
    }
}
