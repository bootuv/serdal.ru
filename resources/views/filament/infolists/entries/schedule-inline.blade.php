<div class="space-y-1">
    <div class="text-sm font-medium text-gray-950 dark:text-white">
        Расписание
    </div>

    @php
        $schedules = $getRecord()->schedules;
        $dayNames = [
            0 => 'Вс',
            1 => 'Пн',
            2 => 'Вт',
            3 => 'Ср',
            4 => 'Чт',
            5 => 'Пт',
            6 => 'Сб'
        ];
    @endphp

    @if($schedules->isEmpty())
        <p class="text-sm text-gray-400">Расписание не настроено</p>
    @else
        <div class="space-y-2">
            @foreach($schedules as $schedule)
                @if($schedule->type === 'once')
                    @php
                        $datetime = \Carbon\Carbon::parse($schedule->scheduled_at);
                    @endphp
                    <div class="flex items-center gap-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg px-3 py-2">
                        <span class="text-base">📅</span>
                        <div class="text-sm">
                            <span class="font-medium">{{ $datetime->format('d.m.Y H:i') }}</span>
                            <span class="text-gray-500 dark:text-gray-400 ml-1">({{ $schedule->duration_minutes ?? 90 }} мин)</span>
                        </div>
                    </div>
                @else
                    @php
                        $days = is_array($schedule->recurrence_days)
                            ? $schedule->recurrence_days
                            : json_decode($schedule->recurrence_days ?? '[]', true);
                        $daysText = collect($days)->map(fn($d) => $dayNames[$d] ?? '')->filter()->join(', ');
                    @endphp
                    <div class="flex items-center gap-2 bg-green-50 dark:bg-green-900/30 rounded-lg px-3 py-2">
                        <span class="text-base">🔄</span>
                        <div class="text-sm">
                            <span class="font-medium">{{ $daysText }}</span>
                            <span class="text-gray-600 dark:text-gray-400 ml-1">в
                                {{ $schedule->recurrence_time ? substr($schedule->recurrence_time, 0, 5) : '' }}</span>
                            <span class="text-gray-500 ml-1">({{ $schedule->duration_minutes ?? 90 }} мин)</span>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>