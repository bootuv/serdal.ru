<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Ближайшие занятия
        </x-slot>

        @if($events->isEmpty())
            <div class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                Нет предстоящих занятий
            </div>
        @else
            <div class="space-y-4">
                @foreach($events as $event)
                    <div
                        class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            @php
                                $colors = [
                                    'once' => '#3b82f6',
                                    'daily' => '#22c55e',
                                    'weekly' => '#f97316',
                                    'monthly' => '#a855f7',
                                ];
                                $bg = $colors[$event['type']] ?? '#6b7280';
                            @endphp
                            <div class="flex flex-col items-center justify-center w-12 h-12 rounded-lg text-white shadow-sm"
                                style="background-color: {{ $bg }};">
                                <span class="text-xs font-bold leading-none">{{ $event['start']->format('d') }}</span>
                                <span
                                    class="text-[10px] font-medium leading-none uppercase mt-0.5">{{ $event['start']->translatedFormat('M') }}</span>
                            </div>

                            <div>
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $event['title'] }}
                                </h4>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                                        {{ $event['start']->translatedFormat('l') }}, {{ $event['start']->format('H:i') }}
                                    </span>
                                    <span
                                        class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                                        {{ $event['duration'] }} мин
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <a href="{{ \App\Filament\App\Resources\RoomResource::getUrl('edit', ['record' => $event['room_id']]) }}"
                                class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                Редактировать
                            </a>
                            <a href="{{ route('rooms.join', $event['room_id']) }}"
                                class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-500 dark:hover:text-primary-300">
                                Перейти
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>