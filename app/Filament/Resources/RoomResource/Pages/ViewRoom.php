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
                ->color(fn() => $this->record->next_start && $this->record->next_start->isPast() && !$this->record->next_start->addMinutes($this->record->duration ?? 45)->isPast() ? 'success' : 'gray')
                ->url(fn() => route('rooms.start', $this->record))
                ->openUrlInNewTab()
                ->visible(function () {
                    if ($this->record->is_running) {
                        return false;
                    }
                    if ($this->record->trashed()) {
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
                ->label('Изменить')
                ->visible(fn() => !$this->record->trashed()),

            Actions\RestoreAction::make()
                ->visible(fn() => $this->record->trashed()),
            Actions\ForceDeleteAction::make()
                ->visible(fn() => $this->record->trashed()),
        ];
    }

    public function getListeners(): array
    {
        return [
            "echo:rooms,.room.status.updated" => '$refresh',
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



                                TextEntry::make('type')
                                    ->label('Тип')
                                    ->formatStateUsing(function (string $state) {
                                        $text = match ($state) {
                                            'individual' => 'Индивидуальное',
                                            'group' => 'Групповое',
                                            default => $state,
                                        };

                                        $isGroup = $state === 'group';
                                        $icon = $isGroup
                                            ? '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>'
                                            : '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>';

                                        $colorClasses = $isGroup
                                            ? 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20 dark:bg-orange-500/10 dark:text-orange-400 dark:ring-orange-400/30'
                                            : 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-400/30';

                                        return new \Illuminate\Support\HtmlString(
                                            "<span class=\"inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-xs font-medium {$colorClasses}\">{$icon} <span>" . e($text) . "</span></span>"
                                        );
                                    }),



                                \Filament\Infolists\Components\ViewEntry::make('next_start')
                                    ->hiddenLabel()
                                    ->view('filament.infolists.next-lesson-status'),
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
