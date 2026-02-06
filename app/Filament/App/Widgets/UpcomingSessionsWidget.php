<?php

namespace App\Filament\App\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Room;

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
                    ->where('user_id', auth()->id())
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
                            $query->where('user_id', auth()->id())->where('is_running', true);
                        });
                    })
            )
            ->heading('Ближайшие занятия')
            ->headerActions([
                Tables\Actions\Action::make('viewAll')
                    ->label('Все занятия')
                    ->url(\App\Filament\App\Resources\RoomResource::getUrl('index'))
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

                Tables\Columns\TextColumn::make('participants_custom')
                    ->label('Ученики')
                    ->getStateUsing(function (Room $record) {
                        $participants = $record->participants;
                        $count = $participants->count();

                        if ($count === 0) {
                            return new \Illuminate\Support\HtmlString('<span class="text-gray-400 dark:text-gray-500 text-xs">Нет учеников</span>');
                        }

                        $avatarsHtml = '<div class="flex -space-x-2 overflow-hidden">';
                        foreach ($participants->take(4) as $participant) {
                            $url = $participant->avatar_url;
                            $name = e($participant->name);
                            $avatarsHtml .= "<img class='inline-block h-6 w-6 rounded-full ring-2 ring-white dark:ring-gray-900 object-cover' src='{$url}' alt='{$name}' title='{$name}' />";
                        }
                        $avatarsHtml .= '</div>';

                        // Russian pluralization
                        $n = abs($count) % 100;
                        $n1 = $n % 10;
                        if ($n > 10 && $n < 20) {
                            $text = $count . ' учеников';
                        } elseif ($n1 > 1 && $n1 < 5) {
                            $text = $count . ' ученика';
                        } elseif ($n1 == 1) {
                            $text = $count . ' ученик';
                        } else {
                            $text = $count . ' учеников';
                        }

                        return new \Illuminate\Support\HtmlString("
                            <div class='flex items-center gap-3'>
                                {$avatarsHtml}
                                <span class='font-medium text-gray-700 dark:text-gray-300 text-sm'>{$text}</span>
                            </div>
                        ");
                    }),

                Tables\Columns\ViewColumn::make('next_start')
                    ->label('Статус')
                    ->view('filament.tables.columns.next-lesson')
                    ->state(fn(Room $record) => $record->next_start?->toIso8601String()),
            ])
            ->emptyStateHeading('Нет занятий в ближайшие 24 часа')
            ->emptyStateDescription('')
            ->recordUrl(fn(Room $record) => \App\Filament\App\Resources\RoomResource::getUrl('view', ['record' => $record]))
            ->paginated(false)
            ->actions([
                Tables\Actions\Action::make('start')
                    ->label('Начать')
                    ->icon('heroicon-o-play')
                    ->color(fn(Room $record) => $record->next_start && $record->next_start->isPast() && !$record->next_start->addMinutes($record->duration ?? 45)->isPast() ? 'success' : 'gray')
                    ->button()
                    ->url(fn(Room $record) => route('rooms.start', $record))
                    ->openUrlInNewTab()
                    ->visible(function (Room $record) {
                        if ($record->is_running) {
                            return false;
                        }

                        $hasOtherRunningMeeting = Room::where('user_id', auth()->id())
                            ->where('is_running', true)
                            ->where('id', '!=', $record->id)
                            ->exists();

                        return !$hasOtherRunningMeeting;
                    }),

                Tables\Actions\Action::make('join')
                    ->label('Присоединиться')
                    ->icon('heroicon-o-user-plus')
                    ->button()
                    ->color('warning')
                    ->url(fn(Room $record) => route('rooms.connect', $record))
                    ->openUrlInNewTab()
                    ->visible(fn(Room $record) => $record->is_running),

                Tables\Actions\Action::make('stop')
                    ->label('Остановить')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->button()
                    ->requiresConfirmation()
                    ->action(fn(Room $record) => redirect()->route('rooms.stop', $record))
                    ->visible(fn(Room $record) => $record->is_running),
            ]);
    }
}
