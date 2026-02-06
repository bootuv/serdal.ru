<?php

namespace App\Filament\App\Resources\RoomResource\Pages;

use App\Filament\App\Resources\RoomResource;
use App\Models\Room;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewRoom extends ViewRecord
{
    protected static string $resource = RoomResource::class;

    public function mount(int|string $record): void
    {
        // Optimization: Throttle BBB sync
        $userId = auth()->id();
        $cacheKey = "bbb_view_sync_throttle_{$userId}";
        $lastSync = \Illuminate\Support\Facades\Cache::get($cacheKey, 0);
        $shouldSync = time() - $lastSync > 10;

        if ($shouldSync) {
            \Illuminate\Support\Facades\Cache::put($cacheKey, time(), 60);
            \App\Jobs\SyncUserBbbStatus::dispatch($userId);
        }

        parent::mount($record);
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getHeading(): string
    {
        return $this->record->name;
    }

    public function copyGuestLinkAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('copyGuestLink')
            ->action(function () {
                \Filament\Notifications\Notification::make()
                    ->title('Ссылка скопирована')
                    ->success()
                    ->send();
            });
    }

    protected function getHeaderActions(): array
    {
        // Refresh the record to get latest is_running state
        $this->record->refresh();

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
                ->url(fn() => \App\Filament\App\Pages\Messenger::getUrl(['room' => $this->record->id])),

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
                    $hasOtherRunningMeeting = Room::where('user_id', auth()->id())
                        ->where('is_running', true)
                        ->where('id', '!=', $this->record->id)
                        ->exists();
                    return !$hasOtherRunningMeeting;
                }),

            Actions\Action::make('stop')
                ->label('Завершить занятие')
                ->icon('heroicon-o-stop')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Завершить занятие?')
                ->modalDescription('Все участники будут отключены от видеоконференции.')
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
            "echo:rooms,.room.status.updated" => 'refreshRoomStatus',
            "echo:rooms,room.status.updated" => 'refreshRoomStatus',
            "echo:rooms,RoomStatusUpdated" => 'refreshRoomStatus',
        ];
    }

    public function refreshRoomStatus(): void
    {
        $this->record->refresh();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Основная информация')
                    ->schema([
                        Grid::make(3)
                            ->schema([


                                TextEntry::make('type')
                                    ->label('Тип')
                                    ->formatStateUsing(function (?string $state) {
                                        if (!$state)
                                            return null;
                                        $text = match ($state) {
                                            'individual' => 'Индивидуальное',
                                            'group' => 'Групповое',
                                            'pending' => 'Не определён',
                                            default => $state,
                                        };

                                        $icon = match ($state) {
                                            'group' => '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>',
                                            'individual' => '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>',
                                            default => '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>',
                                        };

                                        $colorClasses = match ($state) {
                                            'group' => 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20 dark:bg-purple-500/10 dark:text-purple-400 dark:ring-purple-400/30',
                                            'individual' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-400/30',
                                            default => 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20 dark:bg-orange-500/10 dark:text-orange-400 dark:ring-orange-400/30',
                                        };

                                        return new \Illuminate\Support\HtmlString(
                                            "<span class=\"inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-xs font-medium {$colorClasses}\">{$icon} <span>" . e($text) . "</span></span>"
                                        );
                                    }),

                                TextEntry::make('base_price')
                                    ->label('Цена')
                                    ->getStateUsing(function (Room $record) {
                                        $lessonPrice = $record->base_price ?? $record->default_price;
                                        $basePrice = $record->default_price;

                                        if (!$lessonPrice) {
                                            return new HtmlString('<span class="text-gray-400">Не указана</span>');
                                        }

                                        $priceText = number_format($lessonPrice, 0, '', ' ') . ' ₽';

                                        // Check if lesson has custom price that's lower than base
                                        if ($record->base_price !== null && $basePrice && $record->base_price < $basePrice) {
                                            $discount = round((1 - $record->base_price / $basePrice) * 100);
                                            return new HtmlString(
                                                '<span class="font-medium">' . $priceText . '</span> <span class="text-green-600 dark:text-green-400 text-sm ml-1">−' . $discount . '%</span>'
                                            );
                                        }

                                        return new HtmlString('<span class="font-medium">' . $priceText . '</span>');
                                    }),

                                \Filament\Infolists\Components\ViewEntry::make('next_start')
                                    ->hiddenLabel()
                                    ->view('filament.infolists.next-lesson-status'),
                            ]),

                        // Guest invite link + Schedule in 2 columns
                        Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\ViewEntry::make('guest_invite_link')
                                    ->view('filament.infolists.entries.guest-invite-link'),

                                \Filament\Infolists\Components\ViewEntry::make('schedule_inline')
                                    ->view('filament.infolists.entries.schedule-inline'),
                            ]),
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

                                // Current lesson price
                                $lessonPrice = $record->base_price ?? $record->default_price;
                                $defaultPriceLabel = $lessonPrice ? number_format($lessonPrice, 0, '', ' ') . ' ₽' : 'не задана';

                                $html = '<div class="flex flex-wrap gap-2">';
                                foreach ($participants as $participant) {
                                    $customPrice = $participant->pivot->custom_price;

                                    // Calculate discount dynamically relative to lesson price
                                    $discountDisplay = '';
                                    if ($customPrice !== null && $lessonPrice && $customPrice < $lessonPrice) {
                                        $discount = round((1 - $customPrice / $lessonPrice) * 100);
                                        $discountDisplay = ' <span class="text-green-600 dark:text-green-400 text-sm">(-' . $discount . '%)</span>';
                                    }

                                    $priceDisplay = $customPrice !== null
                                        ? '<span class="text-gray-500">' . number_format($customPrice, 0, '', ' ') . ' ₽</span>'
                                        : '<span class="text-gray-500">' . $defaultPriceLabel . '</span>';

                                    $html .= sprintf(
                                        '<div class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-800 rounded-lg px-3 py-2">
                                            <img src="%s" class="w-8 h-8 rounded-full object-cover" alt="%s">
                                            <span class="text-sm font-medium">%s</span>
                                            <span class="ml-1">%s%s</span>
                                        </div>',
                                        e($participant->avatar_url),
                                        e($participant->name),
                                        e($participant->name),
                                        $priceDisplay,
                                        $discountDisplay
                                    );
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('edit_prices')
                            ->label('Изменить цены')
                            ->icon('heroicon-o-currency-dollar')
                            ->color('gray')
                            ->modalHeading('Индивидуальные цены')
                            ->modalWidth('md')
                            ->modalSubmitActionLabel('Сохранить')
                            ->form(function (Room $record) {
                                $participants = $record->participants;
                                if ($participants->isEmpty()) {
                                    return [
                                        \Filament\Forms\Components\Placeholder::make('empty')
                                            ->content('Нет учеников для настройки цен')
                                            ->hiddenLabel(),
                                    ];
                                }

                                // Lesson price (room base_price or teacher's default)
                                $lessonPrice = $record->base_price ?? $record->default_price;
                                // Teacher's base price from profile
                                $teacherBasePrice = $record->default_price;

                                $priceHint = "Цена занятия не задана";
                                if ($lessonPrice) {
                                    $priceHint = "Цена занятия: " . number_format($lessonPrice, 0, '', ' ') . " ₽";
                                    // Show discount if lesson price is lower than teacher's base
                                    if ($record->base_price !== null && $teacherBasePrice && $record->base_price < $teacherBasePrice) {
                                        $discount = round((1 - $record->base_price / $teacherBasePrice) * 100);
                                        $priceHint .= ' <span class="text-green-600 dark:text-green-400">−' . $discount . '%</span>';
                                    }
                                }

                                $fields = [
                                    \Filament\Forms\Components\Placeholder::make('hint')
                                        ->content(new \Illuminate\Support\HtmlString($priceHint))
                                        ->hiddenLabel(),
                                ];

                                foreach ($participants as $participant) {
                                    $avatarHtml = '<div class="flex items-center gap-2"><img src="' . e($participant->avatar_url) . '" class="w-6 h-6 rounded-full object-cover" alt="' . e($participant->name) . '"><span>' . e($participant->name) . '</span></div>';

                                    $fields[] = \Filament\Forms\Components\Section::make(new \Illuminate\Support\HtmlString($avatarHtml))
                                        ->compact()
                                        ->schema([
                                            \Filament\Forms\Components\Grid::make(2)
                                                ->schema([
                                                    \Filament\Forms\Components\TextInput::make("prices.{$participant->id}.custom_price")
                                                        ->hiddenLabel()
                                                        ->numeric()
                                                        ->suffix('₽')
                                                        ->placeholder($lessonPrice ? number_format($lessonPrice, 0, '', ' ') : 'По умолчанию')
                                                        ->default($participant->pivot->custom_price)
                                                        ->live(debounce: 300),
                                                    \Filament\Forms\Components\Placeholder::make("prices.{$participant->id}.discount_display")
                                                        ->hiddenLabel()
                                                        ->extraAttributes(['class' => 'h-10 flex items-center'])
                                                        ->content(function (\Filament\Forms\Get $get) use ($teacherBasePrice, $lessonPrice, $participant) {
                                                            $state = $get("prices.{$participant->id}.custom_price");
                                                            // Calculate discount relative to teacher's base price (total discount)
                                                            $comparePrice = $teacherBasePrice ?? $lessonPrice;
                                                            if (!$comparePrice || !$state || $state >= $comparePrice) {
                                                                return '';
                                                            }
                                                            $discount = round((1 - $state / $comparePrice) * 100);
                                                            return new \Illuminate\Support\HtmlString(
                                                                '<span class="text-green-600 dark:text-green-400 font-medium">Скидка: ' . $discount . '%</span>'
                                                            );
                                                        }),
                                                ]),
                                        ]);
                                }

                                return $fields;
                            })
                            ->action(function (Room $record, array $data) {
                                if (empty($data['prices'])) {
                                    return;
                                }

                                $defaultPrice = $record->getEffectivePrice();

                                foreach ($data['prices'] as $studentId => $priceData) {
                                    $customPrice = $priceData['custom_price'] !== '' ? $priceData['custom_price'] : null;

                                    // Auto-calculate discount note
                                    $priceNote = null;
                                    if ($customPrice !== null && $defaultPrice && $customPrice < $defaultPrice) {
                                        $discount = round((1 - $customPrice / $defaultPrice) * 100);
                                        $priceNote = "Скидка {$discount}%";
                                    }

                                    $record->participants()->updateExistingPivot($studentId, [
                                        'custom_price' => $customPrice,
                                        'price_note' => $priceNote,
                                    ]);
                                }

                                \Filament\Notifications\Notification::make()
                                    ->title('Цены обновлены')
                                    ->success()
                                    ->send();

                                return redirect(request()->header('Referer'));
                            }),
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
                                    $url = \Illuminate\Support\Facades\Storage::disk('s3')->url($file);

                                    // Get size from cache or skip
                                    $size = \Illuminate\Support\Facades\Cache::get("file_size_{$file}");
                                    $sizeDisplay = '';

                                    if ($size) {
                                        $units = ['B', 'KB', 'MB', 'GB'];
                                        $pow = floor(($size ? log($size) : 0) / log(1024));
                                        $pow = min($pow, count($units) - 1);
                                        $size /= (1 << (10 * $pow));
                                        $sizeDisplay = round($size, 1) . ' ' . $units[$pow];
                                    } else {
                                        // Self-healing: if size is missing, dispatch job to cache it
                                        // Prevent duplicate dispatch with a short lock
                                        $lockKey = "dispatch_size_fetch_{$file}";
                                        if (!\Illuminate\Support\Facades\Cache::has($lockKey)) {
                                            \Illuminate\Support\Facades\Cache::put($lockKey, 1, 60);
                                            \App\Jobs\CachePresentationSize::dispatch($file);
                                        }
                                    }

                                    $html .= sprintf(
                                        '<a href="%s" target="_blank" class="flex items-center gap-2 bg-gray-50 dark:bg-gray-800 rounded-lg px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <div class="flex flex-col">
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">%s</span>
                                                <span class="text-xs text-gray-500">%s</span>
                                            </div>
                                        </a>',
                                        e($url),
                                        e($filename),
                                        $sizeDisplay
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
                        'viewUrl' => \App\Filament\App\Resources\MeetingSessionResource::getUrl('view', ['record' => ':id']),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
