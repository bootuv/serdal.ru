<x-filament-panels::page>
    @php
        $currentMonth = request()->get('month', now()->format('Y-m'));
        $filterType = request()->get('type', 'all');
        $date = \Carbon\Carbon::parse($currentMonth . '-01');
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        $startDate = $startOfMonth->copy()->startOfWeek();
        $endDate = $endOfMonth->copy()->endOfWeek();

        // Group events by date and filter by type
        $filteredEvents = $filterType === 'all'
            ? $events
            : $events->filter(fn($e) => $e['room_type'] === $filterType); // Filter by room_type
        $eventsByDate = $filteredEvents->groupBy(fn($event) => $event['start']->format('Y-m-d'));
    @endphp

    {{-- Type Filter using Filament Tabs --}}
    <div class="mb-1">
        <x-filament::tabs>
            <x-filament::tabs.item :active="$filterType === 'all'" :href="'?month=' . $currentMonth . '&type=all'"
                tag="a">
                Все
            </x-filament::tabs.item>

            <x-filament::tabs.item :active="$filterType === 'individual'" :href="'?month=' . $currentMonth . '&type=individual'" tag="a">
                Индивидуальное
            </x-filament::tabs.item>

            <x-filament::tabs.item :active="$filterType === 'group'" :href="'?month=' . $currentMonth . '&type=group'"
                tag="a">
                Групповое
            </x-filament::tabs.item>
        </x-filament::tabs>
    </div>

    {{-- Calendar --}}
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between w-full">
                <h2 class="text-xl font-bold capitalize">
                    {{ $date->locale('ru')->isoFormat('MMMM YYYY') }}
                </h2>
                <div class="flex items-center gap-2">
                    <x-filament::button
                        tag="a"
                        :href="'?month=' . $date->copy()->subMonth()->format('Y-m') . '&type=' . $filterType"
                        color="gray"
                        size="sm"
                        icon="heroicon-m-chevron-left"
                        icon-position="before">
                        Предыдущий
                    </x-filament::button>
                    
                    <x-filament::button
                        tag="a"
                        :href="'?month=' . now()->format('Y-m') . '&type=' . $filterType"
                        size="sm">
                        Сегодня
                    </x-filament::button>
                    
                    <x-filament::button
                        tag="a"
                        :href="'?month=' . $date->copy()->addMonth()->format('Y-m') . '&type=' . $filterType"
                        color="gray"
                        size="sm"
                        icon="heroicon-m-chevron-right"
                        icon-position="after">
                        Следующий
                    </x-filament::button>
                </div>
            </div>
        </x-slot>

        <div class="p-6">
            <div style="border-left-width: 1px;"
                class="grid grid-cols-7 gap-0 border-t border-l border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                @foreach(['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'] as $day)
                    <div style="border-right-width: 1px;"
                        class="bg-gray-100 dark:bg-gray-900 px-3 py-3 text-center border-b border-r border-gray-200 dark:border-gray-700">
                        <span class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            {{ $day }}
                        </span>
                    </div>
                @endforeach

                @php $current = $startDate->copy(); @endphp
                @while($current <= $endDate)
                    @php
                        $isCurrentMonth = $current->month === $date->month;
                        $isToday = $current->isToday();
                        $dateKey = $current->format('Y-m-d');
                        $dayEvents = $eventsByDate->get($dateKey, collect());
                    @endphp

                    <div style="border-right-width: 1px;"
                        class="relative bg-white dark:bg-gray-800 p-2 border-b border-r border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition {{ !$isCurrentMonth ? 'bg-gray-50/50 dark:bg-gray-900/50' : '' }}">
                        <div class="flex items-center justify-center mb-1">
                            <span
                                class="inline-flex items-center justify-center w-6 h-6 text-xs font-normal rounded-full {{ $isToday ? 'bg-primary-600 text-white' : ($isCurrentMonth ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-600') }}">
                                {{ $current->format('d') }}
                            </span>
                        </div>

                        <div class="space-y-1">
                            @foreach($dayEvents as $event)
                                @php
                                    $colors = [
                                        'individual' => [
                                            'bg' => '#2563eb', // blue-600
                                            'text' => '#ffffff', // white
                                            'hover_bg' => '#1d4ed8', // blue-700
                                        ],
                                        'group' => [
                                            'bg' => '#16a34a', // green-600
                                            'text' => '#ffffff', // white
                                            'hover_bg' => '#15803d', // green-700
                                        ],
                                    ];
                                    $color = $colors[$event['room_type']] ?? $colors['individual'];
                                    $isPast = $event['end']->isPast();
                                    // Show join button if: lesson started and not ended, OR it's a manually started room (type='running')
                                    $isOngoing = ($event['start']->isPast() && !$event['end']->isPast()) || ($event['type'] === 'running');
                                @endphp
                                
                                @if($isOngoing)
                                    {{-- Show Join button for ongoing lessons --}}
                                    <x-filament::button
                                        tag="a"
                                        :href="route('rooms.join', $event['room_id'])"
                                        color="warning"
                                        size="xs"
                                        icon="heroicon-m-arrow-right-on-rectangle"
                                        class="w-full justify-center">
                                        Присоединиться
                                    </x-filament::button>
                                @else
                                    {{-- Show event card for future or past lessons --}}
                                    <a href="{{ \App\Filament\App\Resources\RoomResource::getUrl('edit', ['record' => $event['room_id']]) }}"
                                        class="group relative block text-xs px-2 py-1.5 rounded-md cursor-pointer transition-all shadow-sm hover:opacity-90"
                                        style="background-color: {{ $color['bg'] }}; color: {{ $color['text'] }}; {{ $isPast ? 'opacity: 0.35; filter: grayscale(100%);' : '' }}"
                                        onmouseover="this.style.backgroundColor='{{ $color['hover_bg'] }}'"
                                        onmouseout="this.style.backgroundColor='{{ $color['bg'] }}'"
                                        title="{{ $event['title'] }} - {{ $event['start']->format('H:i') }} ({{ $event['duration'] }} мин)">
                                        <div class="font-bold text-[11px] leading-tight">{{ $event['start']->format('H:i') }}</div>
                                        <div class="truncate font-medium text-[10px] leading-tight mt-0.5 opacity-95">
                                            {{ $event['title'] }}
                                        </div>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    @php $current->addDay(); @endphp
                @endwhile
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>