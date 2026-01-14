<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MeetingSessionResource\Pages;
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

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'История сессий';

    protected static ?string $modelLabel = 'Сессия';

    protected static ?string $pluralModelLabel = 'История сессий';

    public static function getNavigationBadge(): ?string
    {
        $count = MeetingSession::whereNotNull('deletion_requested_at')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

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
                Tables\Columns\TextColumn::make('session_cost')
                    ->label('Стоимость занятия')
                    ->state(function (MeetingSession $record) {
                        // Use stored pricing snapshot if available (immutable historical data)
                        if (isset($record->pricing_snapshot['total_cost'])) {
                            return $record->pricing_snapshot['total_cost'];
                        }

                        // Fallback to dynamic calculation for old sessions without snapshot
                        $room = $record->room;
                        if (!$room)
                            return 0;

                        $lessonType = $room->user?->lessonTypes
                                ?->where('type', $room->type)
                            ->first();
                        $paymentType = $lessonType?->payment_type ?? 'per_lesson';

                        $total = 0;
                        if ($paymentType === 'monthly') {
                            foreach ($room->participants as $participant) {
                                $total += $room->getEffectivePrice($participant->id) ?? 0;
                            }
                        } else {
                            $analytics = $record->analytics_data ?? [];
                            $participantsData = $analytics['participants'] ?? [];
                            $attendedIds = collect($participantsData)
                                ->pluck('user_id')
                                ->map(fn($id) => (string) $id)
                                ->toArray();

                            foreach ($room->participants as $participant) {
                                if (in_array((string) $participant->id, $attendedIds)) {
                                    $total += $room->getEffectivePrice($participant->id) ?? 0;
                                }
                            }
                        }
                        return $total;
                    })
                    ->money('RUB')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('deletion_reason')
                    ->label('Причина удаления')
                    ->limit(30)
                    ->tooltip(fn(MeetingSession $record) => $record->deletion_reason)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
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
                Tables\Filters\Filter::make('deletion_requested')
                    ->label('Запрос на удаление')
                    ->query(fn($query) => $query->whereNotNull('deletion_requested_at'))
                    ->indicator('Запрос на удаление'),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::Dropdown)
            ->persistFiltersInSession()
            ->searchable()
            ->actions([
                Tables\Actions\Action::make('deletionRequested')
                    ->label('')
                    ->tooltip(fn(MeetingSession $record) => "Запрос на удаление: {$record->deletion_reason}")
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->url(fn(MeetingSession $record) => MeetingSessionResource::getUrl('view', ['record' => $record]))
                    ->visible(fn(MeetingSession $record) => !is_null($record->deletion_requested_at)),

                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->tooltip('Удалить')
                    ->icon('heroicon-o-trash')
                    ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['room.participants']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMeetingSessions::route('/'),
            'view' => Pages\ViewMeetingSession::route('/{record}'),
        ];
    }
}
