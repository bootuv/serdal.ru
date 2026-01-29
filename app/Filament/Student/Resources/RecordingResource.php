<?php

namespace App\Filament\Student\Resources;

use App\Filament\Student\Resources\RecordingResource\Pages;
use App\Models\Recording;
use App\Models\Room;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecordingResource extends Resource
{
    protected static ?string $model = Recording::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationLabel = 'Записи';

    protected static ?string $modelLabel = 'Запись';

    protected static ?string $pluralModelLabel = 'Записи';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Начало')
                    ->formatStateUsing(fn($state) => format_datetime(\Carbon\Carbon::parse($state)->setTimezone('Europe/Moscow')))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('participants')
                    ->label('Участники')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status_label')
                    ->label('Статус')
                    ->badge()
                    ->getStateUsing(function (Recording $record) {
                        if (!empty($record->vk_video_url)) {
                            return 'Готово';
                        } elseif (!empty($record->url) && str_contains($record->url, '/playback/video/')) {
                            return 'Отправка в VK';
                        } elseif (!empty($record->url)) {
                            // Presentation format - available on BBB
                            return 'Готово';
                        } else {
                            return 'Обработка';
                        }
                    })
                    ->colors([
                        'success' => 'Готово',
                        'info' => 'Отправка в VK',
                        'warning' => 'Обработка',
                    ])
                    ->icons([
                        'heroicon-m-check-circle' => 'Готово',
                        'heroicon-m-arrow-path' => 'Отправка в VK',
                        'heroicon-m-clock' => 'Обработка',
                    ]),
            ])
            ->filters([])
            ->filtersLayout(Tables\Enums\FiltersLayout::Dropdown)
            ->persistFiltersInSession()
            ->searchable()
            ->defaultSort('start_time', 'desc')
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Посмотреть')
                    ->icon('heroicon-m-play')
                    ->color('success')
                    ->url(fn(Recording $record) => static::getUrl('view', ['record' => $record]))
                    ->visible(fn(Recording $record) => !empty($record->vk_video_url)),

                Tables\Actions\Action::make('open_bbb')
                    ->label('Открыть в BBB')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn(Recording $record) => $record->url)
                    ->openUrlInNewTab()
                    ->visible(fn(Recording $record) => empty($record->vk_video_url) && !empty($record->url)),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecordings::route('/'),
            'view' => Pages\ViewRecording::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // Get all teachers of the current student
        $teacherIds = auth()->user()->teachers()->pluck('users.id');

        // Get meeting_ids of all rooms owned by student's teachers
        $teacherRoomMeetingIds = Room::whereIn('user_id', $teacherIds)
            ->pluck('meeting_id')
            ->filter();

        return parent::getEloquentQuery()
            ->whereIn('meeting_id', $teacherRoomMeetingIds)
            // Only show recordings with VK video OR fresh recordings (< 2 hours)
            // This hides stale/deleted recordings that haven't been cleaned up
            ->where(function (Builder $query) {
                $query->whereNotNull('vk_video_url')
                    ->orWhere('start_time', '>', now()->subHours(2));
            });
    }
}
