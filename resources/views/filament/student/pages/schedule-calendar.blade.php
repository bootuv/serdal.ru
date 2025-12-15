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

    {{-- Type Filter --}}
    <div
        class="mb-3 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-4 py-3">
        <div class="flex items-center gap-3">
            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Тип занятия:</span>
            <div class="flex items-center gap-2">
                <a href="?month={{ $currentMonth }}&type=all"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition"
                    style="background-color: {{ $filterType === 'all' ? '#111827' : '#f3f4f6' }}; color: {{ $filterType === 'all' ? '#ffffff' : '#374151' }};">
                    Все
                </a>
                <a href="?month={{ $currentMonth }}&type=individual"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition"
                    style="background-color: {{ $filterType === 'individual' ? '#2563eb' : '#eff6ff' }}; color: {{ $filterType === 'individual' ? '#ffffff' : '#1d4ed8' }};">
                    Индивидуальное
                </a>
                <a href="?month={{ $currentMonth }}&type=group"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition"
                    style="background-color: {{ $filterType === 'group' ? '#16a34a' : '#f0fdf4' }}; color: {{ $filterType === 'group' ? '#ffffff' : '#15803d' }};">
                    Групповое
                </a>
            </div>
        </div>
    </div>

    {{-- Calendar --}}
    <div
        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col">
        <div
            class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex-shrink-0">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white capitalize">
                    {{ $date->locale('ru')->isoFormat('MMMM YYYY') }}
                </h2>
                <div class="flex items-center gap-2">
                    <a href="?month={{ $date->copy()->subMonth()->format('Y-m') }}&type={{ $filterType }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Предыдущий
                    </a>
                    <a href="?month={{ now()->format('Y-m') }}&type={{ $filterType }}"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition">
                        Сегодня
                    </a>
                    <a href="?month={{ $date->copy()->addMonth()->format('Y-m') }}&type={{ $filterType }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        Следующий
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>

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
                                            'text' => '#ffffff',
                                            'hover_bg' => '#1d4ed8', // blue-700
                                        ],
                                        'group' => [
                                            'bg' => '#16a34a', // green-600
                                            'text' => '#ffffff',
                                            'hover_bg' => '#15803d', // green-700
                                        ],
                                    ];
                                    $color = $colors[$event['room_type']] ?? $colors['individual'];
                                    $isPast = $event['end']->isPast();
                                @endphp
                                <div class="group relative block text-xs px-2 py-1.5 rounded-md cursor-default transition-all shadow-sm"
                                    style="background-color: {{ $color['bg'] }}; color: {{ $color['text'] }}; {{ $isPast ? 'opacity: 0.35; filter: grayscale(100%);' : '' }}"
                                    title="{{ $event['title'] }} - {{ $event['start']->format('H:i') }} ({{ $event['duration'] }} мин)">
                                    <div class="font-bold text-[11px] leading-tight">{{ $event['start']->format('H:i') }}</div>
                                    <div class="truncate font-medium text-[10px] leading-tight mt-0.5 opacity-95">
                                        {{ $event['title'] }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @php $current->addDay(); @endphp
                @endwhile
            </div>
        </div>
    </div>
</x-filament-panels::page>