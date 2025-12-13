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

    protected static ?int $navigationSort = 3;

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
                    ->sortable(),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Начало')
                    ->dateTime('d.m.Y H:i')
                    ->timezone('Europe/Moscow')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ended_at')
                    ->label('Конец')
                    ->dateTime('d.m.Y H:i')
                    ->timezone('Europe/Moscow')
                    ->sortable()
                    ->placeholder('Запущена...'),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Длительность')
                    ->state(function (MeetingSession $record) {
                        if (!$record->ended_at) {
                            return $record->started_at->diffForHumans(now(), true) . ' (Активна)';
                        }
                        return $record->started_at->diffForHumans($record->ended_at, true);
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'running' => 'success',
                        'completed' => 'gray',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('analytics')
                    ->label('Отчет')
                    ->icon('heroicon-o-chart-bar')
                    ->url(function (MeetingSession $record) {
                        if (!$record->internal_meeting_id) {
                            return null;
                        }

                        // Get BBB Host from Settings or Config
                        $bbbUrl = config('bigbluebutton.BBB_SERVER_BASE_URL');
                        $user = auth()->user();
                        if ($user && $user->bbb_url) {
                            $bbbUrl = $user->bbb_url;
                        } else {
                            $globalUrl = \App\Models\Setting::where('key', 'bbb_url')->value('value');
                            if ($globalUrl) {
                                $bbbUrl = $globalUrl;
                            }
                        }

                        // Parse Host
                        $host = parse_url($bbbUrl, PHP_URL_HOST);
                        $scheme = parse_url($bbbUrl, PHP_URL_SCHEME) ?? 'https';

                        return "{$scheme}://{$host}/learning-analytics-dashboard/?meeting={$record->internal_meeting_id}&lang=ru";
                    })
                    ->openUrlInNewTab()
                    ->visible(fn(MeetingSession $record) => !empty($record->internal_meeting_id)),
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
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }
}
