<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
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
            Actions\Action::make('start')
                ->label('Начать занятие')
                ->icon('heroicon-o-play')
                ->color('success')
                ->url(fn() => route('rooms.start', $this->record))
                ->openUrlInNewTab()
                ->visible(function () {
                    if ($this->record->is_running) {
                        return false;
                    }
                    if ($this->record->user_id !== auth()->id()) {
                        return false;
                    }
                    $hasOtherRunningMeeting = Room::where('user_id', auth()->id())
                        ->where('is_running', true)
                        ->where('id', '!=', $this->record->id)
                        ->exists();
                    return !$hasOtherRunningMeeting;
                }),

            Actions\Action::make('stop')
                ->label('Остановить')
                ->icon('heroicon-o-stop')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn() => redirect()->route('rooms.stop', $this->record))
                ->visible(fn() => $this->record->is_running),

            Actions\EditAction::make()
                ->label('Изменить'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Основная информация')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Владелец'),

                                TextEntry::make('name')
                                    ->label('Название'),

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
                                    ->formatStateUsing(fn(bool $state) => $state ? 'Идет урок' : 'Не запущено')
                                    ->badge()
                                    ->color(fn(bool $state) => $state ? 'warning' : 'gray')
                                    ->icon(fn(bool $state) => $state ? 'heroicon-m-video-camera' : 'heroicon-m-clock'),

                                TextEntry::make('created_at')
                                    ->label('Создано')
                                    ->dateTime('d.m.Y H:i'),
                            ]),

                        TextEntry::make('welcome_msg')
                            ->label('Приветственное сообщение')
                            ->placeholder('Не указано')
                            ->columnSpanFull(),
                    ]),

                Section::make('Ученики')
                    ->schema([
                        TextEntry::make('participants')
                            ->label('')
                            ->getStateUsing(function (Room $record) {
                                $participants = $record->participants;
                                if ($participants->isEmpty()) {
                                    return new HtmlString('<span class="text-gray-400">Ученики не добавлены</span>');
                                }

                                $html = '<div class="flex flex-wrap gap-3">';
                                foreach ($participants as $participant) {
                                    $html .= sprintf(
                                        '<div class="flex items-center gap-2 bg-gray-100 dark:bg-gray-800 rounded-lg px-3 py-2">
                                            <img src="%s" class="w-8 h-8 rounded-full object-cover" alt="%s">
                                            <span class="text-sm font-medium">%s</span>
                                        </div>',
                                        e($participant->avatar_url),
                                        e($participant->name),
                                        e($participant->name)
                                    );
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

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

                Section::make('Презентации')
                    ->schema([
                        TextEntry::make('presentations')
                            ->label('')
                            ->getStateUsing(function (Room $record) {
                                $presentations = $record->presentations;
                                if (empty($presentations)) {
                                    return new HtmlString('<span class="text-gray-400">Презентации не загружены</span>');
                                }

                                $html = '<div class="space-y-2">';
                                foreach ($presentations as $file) {
                                    $filename = basename($file);
                                    $html .= sprintf(
                                        '<div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-800 rounded-lg px-3 py-2">
                                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <span class="text-sm">%s</span>
                                        </div>',
                                        e($filename)
                                    );
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                \Filament\Infolists\Components\ViewEntry::make('session_history')
                    ->view('filament.components.session-history')
                    ->viewData([
                        'roomId' => $this->record->id,
                        'viewUrl' => \App\Filament\Resources\MeetingSessionResource::getUrl('view', ['record' => ':id']),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
