<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MeetingSessionResource\Pages;
use App\Models\MeetingSession;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MeetingSessionResource extends Resource
{
    protected static ?string $model = MeetingSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'История сессий';

    protected static ?string $modelLabel = 'Сессия';

    protected static ?string $pluralModelLabel = 'История сессий';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('meeting_id')
                    ->label('ID Встречи')
                    ->disabled(),
                Forms\Components\TextInput::make('status')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('started_at')
                    ->label('Начало')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('ended_at')
                    ->label('Конец')
                    ->disabled(),
                Forms\Components\KeyValue::make('settings_snapshot')
                    ->label('Параметры запуска')
                    ->columnSpanFull()
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Создатель')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('room.name')
                    ->label('Занятие')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('participant_count')
                    ->label('Участники')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Начало')
                    ->dateTime('d.m.Y H:i')
                    ->timezone('Europe/Moscow')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ended_at')
                    ->label('Конец')
                    ->dateTime('d.m.Y H:i')
                    ->timezone('Europe/Moscow')
                    ->sortable()
                    ->placeholder('Запущена...')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Длительность')
                    ->state(function (MeetingSession $record) {
                        if (!$record->ended_at) {
                            return $record->started_at->diffForHumans(now(), true) . ' (Активна)';
                        }
                        return $record->started_at->diffForHumans($record->ended_at, true);
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'running' => 'success',
                        'completed' => 'gray',
                        default => 'warning',
                    })
                    ->toggleable(),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('user')
                    ->label('Создатель')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('room')
                    ->label('Занятие')
                    ->relationship('room', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'running' => 'Активна',
                        'completed' => 'Завершена',
                    ]),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::Dropdown)
            ->persistFiltersInSession()
            ->searchable()
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Отчет')
                    ->icon('heroicon-o-chart-bar'),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMeetingSessions::route('/'),
            'view' => Pages\ViewMeetingSession::route('/{record}'),
        ];
    }
}
