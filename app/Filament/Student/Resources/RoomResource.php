<?php

namespace App\Filament\Student\Resources;

use App\Filament\Student\Resources\RoomResource\Pages;
use App\Models\Room;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';



    protected static ?string $navigationLabel = 'Занятия';

    protected static ?string $modelLabel = 'Занятие';

    protected static ?string $pluralModelLabel = 'Занятия';

    protected static ?int $navigationSort = 1;

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
            ->query(
                Room::query()->whereHas('participants', function (Builder $query) {
                    $query->where('users.id', auth()->id());
                })
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->icon(fn(Room $record) => $record->type === 'group' ? 'heroicon-o-user-group' : 'heroicon-o-user')
                    ->iconColor('gray'),
                Tables\Columns\ViewColumn::make('user')
                    ->label('Преподаватель')
                    ->view('filament.tables.columns.teacher-avatar')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->join('users', 'rooms.user_id', '=', 'users.id')
                            ->orderBy('users.name', $direction)
                            ->select('rooms.*');
                    })
                    ->toggleable(),
                Tables\Columns\ViewColumn::make('next_start')
                    ->label('Статус')
                    ->view('filament.tables.columns.next-lesson')
                    ->sortable()
                    ->state(fn(Room $record) => $record->next_start?->toIso8601String()),
            ])
            ->searchable()
            ->actions([
                Tables\Actions\Action::make('join')
                    ->label('Присоединиться')
                    ->url(fn(Room $record) => route('rooms.join', $record))
                    ->button()
                    ->color('warning')
                    ->icon('heroicon-m-arrow-right-end-on-rectangle')
                    ->visible(fn(Room $record) => $record->is_running)
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRooms::route('/'),
            'view' => Pages\ViewRoom::route('/{record}'),
        ];
    }
}
