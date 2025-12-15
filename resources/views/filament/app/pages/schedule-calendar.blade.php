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
            <div class="grid grid-cols-7 gap-0 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                @foreach(['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'] as $day)
                    <div
                        class="bg-gray-100 dark:bg-gray-900 px-3 py-3 text-center border-b border-r border-gray-200 dark:border-gray-700 last:border-r-0">
                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            {{ mb_substr($day, 0, 2) }}
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

                    <div
                        class="relative bg-white dark:bg-gray-800 p-2 border-b border-r border-gray-200 dark:border-gray-700 last:border-r-0 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition {{ !$isCurrentMonth ? 'bg-gray-50/50 dark:bg-gray-900/50' : '' }}">
                        <div class="flex items-center justify-between mb-1">
                            <span
                                class="inline-flex items-center justify-center w-7 h-7 text-sm font-semibold rounded-full {{ $isToday ? 'bg-primary-600 text-white' : ($isCurrentMonth ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-600') }}">
                                {{ $current->format('d') }}
                            </span>
                        </div>

                        <div class="space-y-1">
                            @foreach($dayEvents->take(3) as $event)
                                @php
                                    $colors = [
                                        'individual' => [
                                            'bg' => '#eff6ff',
                                            'text' => '#1d4ed8',
                                            'hover_bg' => '#dbeafe',
                                        ],
                                        'group' => [
                                            'bg' => '#f0fdf4',
                                            'text' => '#15803d',
                                            'hover_bg' => '#dcfce7',
                                        ],
                                    ];
                                    $color = $colors[$event['room_type']] ?? $colors['individual'];
                                @endphp
                                <a href="{{ \App\Filament\App\Resources\RoomResource::getUrl('edit', ['record' => $event['room_id']]) }}"
                                    class="group relative block text-xs px-2 py-1.5 rounded-md cursor-pointer transition-all shadow-sm hover:opacity-90"
                                    style="background-color: {{ $color['bg'] }}; color: {{ $color['text'] }};"
                                    onmouseover="this.style.backgroundColor='{{ $color['hover_bg'] }}'"
                                    onmouseout="this.style.backgroundColor='{{ $color['bg'] }}'"
                                    title="{{ $event['title'] }} - {{ $event['start']->format('H:i') }} ({{ $event['duration'] }} мин)">
                                    <div class="font-bold text-[11px] leading-tight">{{ $event['start']->format('H:i') }}</div>
                                    <div class="truncate font-medium text-[10px] leading-tight mt-0.5 opacity-95">
                                        {{ $event['title'] }}
                                    </div>
                                </a>
                            @endforeach

                            @if($dayEvents->count() > 3)
                                <div class="text-[10px] text-gray-600 dark:text-gray-400 font-medium px-1 text-center">
                                    +{{ $dayEvents->count() - 3 }}
                                </div>
                            @endif
                        </div>
                    </div>

                    @php $current->addDay(); @endphp
                @endwhile
            </div>
        </div>
    </div>
</x-filament-panels::page>