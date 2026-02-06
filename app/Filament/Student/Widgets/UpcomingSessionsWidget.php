<?php

namespace App\Filament\Student\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;

class UpcomingSessionsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getListeners(): array
    {
        return [
            "echo:rooms,.room.status.updated" => '$refresh',
            "echo:rooms,room.status.updated" => '$refresh',
            "echo:rooms,RoomStatusUpdated" => '$refresh',
        ];
    }

    public function table(Table $table): Table
    {
        $now = now();
        $in24Hours = $now->copy()->addHours(24);

        return $table
            ->query(
                Room::query()
                    ->whereHas('participants', function (Builder $query) {
                        $query->where('users.id', auth()->id());
                    })
                    ->get()
                    ->filter(function ($room) use ($now, $in24Hours) {
                        if (!$room->next_start) {
                            return false;
                        }

                        $duration = $room->duration ?? 45;
                        $endTime = $room->next_start->copy()->addMinutes($duration);

                        $isUpcoming = $room->next_start->gte($now) && $room->next_start->lte($in24Hours);
                        $isInProgress = $room->next_start->lte($now) && $endTime->gte($now);

                        return $isUpcoming || $isInProgress;
                    })
                    ->sortBy('next_start')
                    ->take(5)
                    ->pluck('id')
                    ->pipe(function ($ids) {
                        return Room::whereIn('id', $ids)->orWhere(function ($query) {
                            $query->whereHas('participants', function (Builder $q) {
                                $q->where('users.id', auth()->id());
                            })->where('is_running', true);
                        });
                    })
            )
            ->heading('Ближайшие занятия')
            ->headerActions([
                Tables\Actions\Action::make('viewAll')
                    ->label('Все занятия')
                    ->url(\App\Filament\Student\Resources\RoomResource::getUrl('index'))
                    ->link(),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->formatStateUsing(function (string $state, Room $record) {
                        $isGroup = $record->type === 'group';
                        $icon = $isGroup
                            ? '<svg class="w-5 h-5 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>'
                            : '<svg class="w-5 h-5 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>';

                        return new \Illuminate\Support\HtmlString(
                            '<div class="flex items-center gap-2">' . $icon . '<span>' . e($state) . '</span></div>'
                        );
                    }),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Учитель')
                    ->formatStateUsing(function ($state, Room $record) {
                        return new \Illuminate\Support\HtmlString(
                            '<div class="flex items-center gap-2">
                                <img class="inline-block h-6 w-6 rounded-full object-cover" src="' . $record->user->avatar_url . '" alt="' . e($record->user->name) . '">
                                <span>' . e($state) . '</span>
                            </div>'
                        );
                    }),

                Tables\Columns\ViewColumn::make('next_start')
                    ->label('Статус')
                    ->view('filament.tables.columns.next-lesson')
                    ->viewData(['isStudent' => true])
                    ->state(fn(Room $record) => $record->next_start?->toIso8601String()),
            ])
            ->emptyStateHeading('Нет занятий в ближайшие 24 часа')
            ->emptyStateDescription('')
            ->recordUrl(fn(Room $record) => \App\Filament\Student\Resources\RoomResource::getUrl('view', ['record' => $record]))
            ->paginated(false)
            ->actions([
                Tables\Actions\Action::make('join')
                    ->label('Присоединиться')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->button()
                    ->color('warning')
                    ->url(fn(Room $record) => route('rooms.connect', $record))
                    ->openUrlInNewTab()
                    ->visible(fn(Room $record) => $record->is_running),
            ]);
    }
}
