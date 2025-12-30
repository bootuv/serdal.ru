<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\MeetingSessionResource\Pages;
use App\Models\MeetingSession;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MeetingSessionResource extends Resource
{
    protected static ?string $model = MeetingSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'История сессий';

    protected static ?string $modelLabel = 'Сессия';

    protected static ?string $pluralModelLabel = 'История сессий';

    protected static ?int $navigationSort = 6;

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
                    ->html()
                    ->state(function (MeetingSession $record) {
                        $stats = $record->getStudentAttendance();
                        $color = $stats['color'];
                        $text = "{$stats['attended']}/{$stats['total']}";
                        // Use the same inline style approach as the blade view
                        return "<span class=\"inline-flex items-center justify-center -my-1 mx-auto min-h-6 min-w-6 px-2 py-0.5 rounded-full text-xs font-medium\" style=\"color: {$color}; background-color: {$color}1A;\">{$text}</span>";
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->timezone('Europe/Moscow')
                    ->sortable()
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
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('room')
                    ->label('Занятие')
                    ->relationship('room', 'name', fn(Builder $query) => $query->where('user_id', auth()->id()))
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
                //
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id())
            ->with(['room.participants']);
    }
}
