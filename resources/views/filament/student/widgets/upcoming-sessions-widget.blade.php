<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Ближайшие занятия
        </x-slot>
        <x-slot name="headerEnd">
            <a href="{{ $roomsUrl }}"
                class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">
                Все занятия →
            </a>
        </x-slot>

        @if($rooms->isEmpty())
            <div class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                Нет занятий в ближайшие 24 часа
            </div>
        @else
            <div class="overflow-x-auto -mx-4 sm:-mx-6">
                <table class="w-full">
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($rooms as $room)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5 cursor-pointer"
                                onclick="window.location='{{ \App\Filament\Student\Resources\RoomResource::getUrl('view', ['record' => $room]) }}'">
                                {{-- Column 1: Name --}}
                                <td class="px-4 sm:px-6 py-3 whitespace-nowrap">
                                    <a href="{{ \App\Filament\Student\Resources\RoomResource::getUrl('view', ['record' => $room]) }}"
                                        class="text-sm font-medium text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400">{{ $room->name }}</a>
                                </td>

                                {{-- Column 2: Teacher --}}
                                <td class="px-4 sm:px-6 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <img class="inline-block h-6 w-6 rounded-full object-cover"
                                            src="{{ $room->user->avatar_url }}" alt="{{ $room->user->name }}">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $room->user->name }}</span>
                                    </div>
                                </td>

                                {{-- Column 3: Status / Timer --}}
                                <td class="px-4 sm:px-6 py-3 whitespace-nowrap">
                                    @if($room->is_running)
                                        <x-filament::badge color="success" icon="heroicon-m-video-camera">
                                            Идет урок
                                        </x-filament::badge>
                                    @elseif(!$room->next_start)
                                        <span class="text-gray-400 dark:text-gray-500 text-sm">Нет расписания</span>
                                    @else
                                        <div x-data="{
                                                                                target: '{{ $room->next_start->toIso8601String() }}',
                                                                                duration: {{ ((int) $room->duration > 0 ? (int) $room->duration : 45) * 60 * 1000 }},
                                                                                now: new Date(),
                                                                                get diff() { return new Date(this.target) - this.now; },
                                                                                get days() { return Math.floor(this.diff / (1000 * 60 * 60 * 24)); },
                                                                                get totalHours() { return Math.floor(this.diff / (1000 * 60 * 60)); },
                                                                                get hours() { return Math.floor((this.diff / (1000 * 60 * 60)) % 24); },
                                                                                get minutes() { return Math.floor((this.diff / 1000 / 60) % 60); },
                                                                                get seconds() { return Math.floor((this.diff / 1000) % 60); },
                                                                                get isTime() { return this.diff <= 0 && this.diff > -(this.duration); },
                                                                                get isNear() { return this.diff > 0 && this.diff < 30 * 60 * 1000; },
                                                                                get isToday() { return this.diff > 0 && this.totalHours < 24; },
                                                                                plural(number, titles) {
                                                                                    const cases = [2, 0, 1, 1, 1, 2];
                                                                                    return titles[(number % 100 > 4 && number % 100 < 20) ? 2 : cases[(number % 10 < 5) ? number % 10 : 5]];
                                                                                }
                                                                            }" x-init="setInterval(() => now = new Date(), 1000)">
                                            <template x-if="isTime">
                                                <span
                                                    class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1"
                                                    style="background-color: rgb(220 252 231); color: rgb(22 101 52); --tw-ring-color: rgb(134 239 172 / 0.2);">
                                                    Скоро начнётся
                                                </span>
                                            </template>
                                            <template x-if="!isTime && diff > 0">
                                                <div class="flex flex-col">
                                                    <span class="text-gray-500 dark:text-gray-400 text-xs">Старт через:</span>
                                                    <template x-if="isNear">
                                                        <span
                                                            class="font-medium text-sm tabular-nums text-orange-600 dark:text-orange-400">
                                                            <span x-text="String(hours).padStart(2, '0')"></span>:<span
                                                                x-text="String(minutes).padStart(2, '0')"></span>:<span
                                                                x-text="String(seconds).padStart(2, '0')"></span>
                                                        </span>
                                                    </template>
                                                    <template x-if="!isNear && isToday">
                                                        <span class="font-medium text-gray-700 dark:text-gray-200 text-sm">
                                                            <span x-text="totalHours"></span>
                                                            <span x-text="plural(totalHours, ['час', 'часа', 'часов'])"></span>
                                                        </span>
                                                    </template>
                                                    <template x-if="!isNear && !isToday">
                                                        <span class="font-medium text-gray-700 dark:text-gray-200 text-sm">
                                                            <span x-text="days"></span>
                                                            <span x-text="plural(days, ['день', 'дня', 'дней'])"></span>
                                                        </span>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    @endif
                                </td>

                                {{-- Column 4: Actions --}}
                                <td class="px-4 sm:px-6 py-3 whitespace-nowrap text-right" onclick="event.stopPropagation()">
                                    @if($room->is_running)
                                        <x-filament::button tag="a" href="{{ route('rooms.join', $room) }}" target="_blank"
                                            color="warning" icon="heroicon-o-arrow-right-end-on-rectangle" size="sm">
                                            Присоединиться
                                        </x-filament::button>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500 text-sm">Ожидание</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>