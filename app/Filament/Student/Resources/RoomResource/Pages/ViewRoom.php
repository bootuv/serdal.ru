<?php

namespace App\Filament\Student\Resources\RoomResource\Pages;

use App\Filament\Student\Resources\RoomResource;
use App\Models\Room;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewRoom extends ViewRecord
{
    protected static string $resource = RoomResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getHeading(): string
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('chat')
                ->label('')
                ->tooltip('Чат занятия')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->badge(function () {
                    $unreadCount = $this->record->messages()
                        ->where('user_id', '!=', auth()->id())
                        ->whereNull('read_at')
                        ->count();
                    return $unreadCount > 0 ? $unreadCount : null;
                })
                ->badgeColor('warning')
                ->url(fn() => \App\Filament\Student\Pages\Messenger::getUrl(['room' => $this->record->id])),

            Actions\Action::make('join')
                ->label('Присоединиться')
                ->icon('heroicon-o-user-plus')
                ->color('warning')
                ->url(fn() => route('rooms.join', $this->record))
                ->openUrlInNewTab()
                ->visible(fn() => $this->record->is_running),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Информация о занятии')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Название'),

                                TextEntry::make('user.name')
                                    ->label('Преподаватель'),

                                TextEntry::make('type')
                                    ->label('Тип')
                                    ->formatStateUsing(fn(string $state) => match ($state) {
                                        'individual' => 'Индивидуальное',
                                        'group' => 'Групповое',
                                        default => $state,
                                    })
                                    ->badge()
                                    ->color(fn(string $state) => match ($state) {
                                        'individual' => 'info',
                                        'group' => 'success',
                                        default => 'gray',
                                    }),

                                TextEntry::make('is_running')
                                    ->label('Статус')
                                    ->formatStateUsing(fn(bool $state) => $state ? 'Идет урок' : 'Ожидание')
                                    ->badge()
                                    ->color(fn(bool $state) => $state ? 'warning' : 'gray')
                                    ->icon(fn(bool $state) => $state ? 'heroicon-m-video-camera' : 'heroicon-m-clock'),
                            ]),

                        TextEntry::make('welcome_msg')
                            ->label('Приветственное сообщение')
                            ->placeholder('Не указано')
                            ->columnSpanFull(),
                    ]),

                Section::make('Расписание')
                    ->schema([
                        TextEntry::make('schedules')
                            ->label('')
                            ->getStateUsing(function (Room $record) {
                                $schedules = $record->schedules;
                                if ($schedules->isEmpty()) {
                                    return new HtmlString('<span class="text-gray-400">Расписание не настроено</span>');
                                }

                                $html = '<div class="space-y-3">';
                                foreach ($schedules as $schedule) {
                                    if ($schedule->type === 'once') {
                                        $datetime = \Carbon\Carbon::parse($schedule->scheduled_at);
                                        $html .= sprintf(
                                            '<div class="flex items-center gap-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg px-4 py-3">
                                                <span class="text-lg">📅</span>
                                                <div>
                                                    <span class="font-medium">Одноразовое занятие</span>
                                                    <span class="text-gray-600 dark:text-gray-400 ml-2">%s</span>
                                                    <span class="text-sm text-gray-500 ml-2">(%d мин)</span>
                                                </div>
                                            </div>',
                                            $datetime->format('d.m.Y H:i'),
                                            $schedule->duration_minutes ?? 90
                                        );
                                    } else {
                                        $days = is_array($schedule->recurrence_days)
                                            ? $schedule->recurrence_days
                                            : json_decode($schedule->recurrence_days ?? '[]', true);

                                        $dayNames = [
                                            0 => 'Вс',
                                            1 => 'Пн',
                                            2 => 'Вт',
                                            3 => 'Ср',
                                            4 => 'Чт',
                                            5 => 'Пт',
                                            6 => 'Сб'
                                        ];

                                        $daysText = collect($days)
                                            ->map(fn($d) => $dayNames[$d] ?? '')
                                            ->filter()
                                            ->join(', ');

                                        $html .= sprintf(
                                            '<div class="flex items-center gap-2 bg-green-50 dark:bg-green-900/30 rounded-lg px-4 py-3">
                                                <span class="text-lg">🔄</span>
                                                <div>
                                                    <span class="font-medium">Еженедельно</span>
                                                    <span class="text-gray-600 dark:text-gray-400 ml-2">%s в %s</span>
                                                    <span class="text-sm text-gray-500 ml-2">(%d мин)</span>
                                                </div>
                                            </div>',
                                            $daysText,
                                            $schedule->recurrence_time ? substr($schedule->recurrence_time, 0, 5) : '',
                                            $schedule->duration_minutes ?? 90
                                        );
                                    }
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                \Filament\Infolists\Components\ViewEntry::make('session_history')
                    ->view('filament.components.session-history')
                    ->viewData([
                        'roomId' => $this->record->id,
                        'viewUrl' => null,
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
