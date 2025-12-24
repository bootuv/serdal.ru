<x-filament-panels::page>
    <div class="flex flex-col md:flex-row gap-6" style="height: calc(100vh - 14rem);">
        {{-- Список занятий --}}
        <div class="md:flex-shrink-0 flex flex-col" style="min-width: 400px;">
            <x-filament::section class="flex-1 flex flex-col">
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-m-chat-bubble-left-right class="w-5 h-5 text-primary-500" />
                        Мои занятия
                    </div>
                </x-slot>

                <div class="space-y-2 -mx-4 -mb-4 flex-1 overflow-y-auto">
                    @forelse($rooms as $room)
                        <button wire:click="selectRoom({{ $room->id }})" @class([
                            'w-full px-4 py-3 flex items-center gap-3 text-left transition-colors',
                            'bg-primary-50 dark:bg-primary-500/10 border-l-4 border-primary-500' => $selectedRoomId === $room->id,
                            'hover:bg-gray-50 dark:hover:bg-white/5 border-l-4 border-transparent' => $selectedRoomId !== $room->id,
                        ])>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg"
                                style="background-color: {{ $room->avatar_bg_color }}; color: {{ $room->avatar_text_color }}">
                                {{ mb_substr($room->name, 0, 1) }}
                            </div>

                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-950 dark:text-white truncate">
                                    {{ $room->name }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                    @if($room->messages->isNotEmpty())
                                        {{ \Illuminate\Support\Str::limit($room->messages->first()->content, 25) }}
                                    @else
                                        Нет сообщений
                                    @endif
                                </p>
                            </div>

                            @php
                                $unreadCount = $room->messages->where('read_at', null)->where('user_id', '!=', auth()->id())->count();
                            @endphp

                            @if($unreadCount > 0)
                                <x-filament::badge color="primary" size="sm">
                                    {{ $unreadCount }}
                                </x-filament::badge>
                            @endif
                        </button>
                    @empty
                        <div class="px-4 py-8 text-center">
                            <x-heroicon-o-inbox class="w-10 h-10 mx-auto text-gray-400 dark:text-gray-500" />
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Нет занятий</p>
                        </div>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        {{-- Область чата --}}
        <div class="flex-1 min-w-0 flex flex-col">
            @if($selectedRoom)
                <livewire:room-chat :room="$selectedRoom" :key="'chat-' . $selectedRoom->id" />
            @else
                <div
                    class="flex-1 flex items-center justify-center bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                    <div class="text-center">
                        <x-heroicon-o-chat-bubble-left-right class="mx-auto text-gray-400 dark:text-gray-500"
                            style="width: 64px; height: 64px;" />
                        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Выберите занятие</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Чтобы начать переписку с учениками</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>