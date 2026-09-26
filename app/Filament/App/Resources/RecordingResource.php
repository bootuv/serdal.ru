<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\RecordingResource\Pages;
use App\Models\Recording;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

class RecordingResource extends Resource
{
    protected static ?string $model = Recording::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationLabel = 'Записи';

    protected static ?string $modelLabel = 'Запись';

    protected static ?string $pluralModelLabel = 'Записи';

    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Название')
                    ->disabled(),
                Forms\Components\TextInput::make('meeting_id')
                    ->label('ID встречи')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
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
                        if (!empty($record->s3_url)) {
                            return 'Готово';
                        } elseif (!empty($record->url) && str_contains($record->url, '/playback/video/')) {
                            return 'Загрузка';
                        } elseif (!empty($record->url)) {
                            return 'Готово';
                        } else {
                            return 'Обработка';
                        }
                    })
                    ->colors([
                        'success' => 'Готово',
                        'info' => 'Загрузка',
                        'warning' => 'Обработка',
                    ])
                    ->icons([
                        'heroicon-m-check-circle' => 'Готово',
                        'heroicon-m-arrow-path' => 'Загрузка',
                        'heroicon-m-clock' => 'Обработка',
                    ]),
            ])
            ->filters([])
            ->searchable()
            ->defaultSort('start_time', 'desc')
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Посмотреть')
                    ->icon('heroicon-m-play')
                    ->color('success')
                    ->url(fn(Recording $record) => static::getUrl('view', ['record' => $record]))
                    ->visible(fn(Recording $record) => !empty($record->s3_url)),

                Tables\Actions\Action::make('download')
                    ->label('Скачать')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('gray')
                    ->url(fn(Recording $record) => route('recordings.download', $record))
                    ->visible(fn(Recording $record) => !empty($record->s3_url)),

                Tables\Actions\Action::make('open_bbb')
                    ->label('Открыть в BBB')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn(Recording $record) => $record->url)
                    ->openUrlInNewTab()
                    ->visible(fn(Recording $record) => empty($record->s3_url) && !empty($record->url)),

                Tables\Actions\DeleteAction::make()
                    // Сначала удаляем с сервера занятий (общий сервис с новым кабинетом)
                    ->before(fn (Recording $record) => app(\App\Services\TeacherRecordingsService::class)->deleteFromServer($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
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
        // Записи занятий учителя
        return parent::getEloquentQuery()->forTeacher(auth()->user());
    }
}
