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
                    ->dateTime()
                    ->timezone('Europe/Moscow')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('participants')
                    ->label('Участники')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('published')
                    ->label('Опубликовано')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('published')
                    ->label('Опубликовано'),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::Dropdown)
            ->persistFiltersInSession()
            ->searchable()
            ->defaultSort('start_time', 'desc')
            ->actions([
                Tables\Actions\Action::make('play')
                    ->label('Смотреть')
                    ->icon('heroicon-o-play')
                    ->url(fn(Recording $record) => $record->url)
                    ->openUrlInNewTab()
                    ->visible(fn(Recording $record) => !empty($record->url)),
                Tables\Actions\Action::make('download')
                    ->label('Скачать MP4')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(function (Recording $record) {
                        // Extract MP4 URL from raw_data
                        if (!empty($record->raw_data['playback']['format'])) {
                            $formats = $record->raw_data['playback']['format'];
                            // Handle single format or array of formats
                            if (!isset($formats[0])) {
                                $formats = [$formats];
                            }
                            foreach ($formats as $format) {
                                if (isset($format['type']) && $format['type'] === 'video' && isset($format['url'])) {
                                    return $format['url'];
                                }
                            }
                        }
                        return null;
                    })
                    ->openUrlInNewTab()
                    ->visible(function (Recording $record) {
                        if (!empty($record->raw_data['playback']['format'])) {
                            $formats = $record->raw_data['playback']['format'];
                            if (!isset($formats[0])) {
                                $formats = [$formats];
                            }
                            foreach ($formats as $format) {
                                if (isset($format['type']) && $format['type'] === 'video') {
                                    return true;
                                }
                            }
                        }
                        return false;
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecordings::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // Get meeting_ids of rooms where the student is a participant
        $studentRoomMeetingIds = Room::query()
            ->whereHas('participants', function (Builder $query) {
                $query->where('users.id', auth()->id());
            })
            ->pluck('meeting_id')
            ->filter();

        return parent::getEloquentQuery()->whereIn('meeting_id', $studentRoomMeetingIds);
    }
}
