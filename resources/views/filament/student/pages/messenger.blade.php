<x-filament-panels::page>
    <div class="flex flex-col md:flex-row gap-6" style="height: calc(100vh - 10rem);">
        {{-- Список чатов --}}
        <div class="md:flex-shrink-0 flex flex-col" style="min-width: 400px;">
            <x-filament::section class="flex-1 flex flex-col">
                <x-slot name="heading">
                    <div class="flex items-center justify-between w-full">
                        <span>Сообщения</span>
                        <x-filament::icon-button wire:click="selectSupportChat" icon="heroicon-m-lifebuoy" color="gray"
                            label="Техническая поддержка" tooltip="Техническая поддержка" :badge="$supportUnreadCount > 0 ? ($supportUnreadCount > 9 ? '9+' : $supportUnreadCount) : null" badge-color="primary" />
                    </div>
                </x-slot>

                <div class="space-y-2 -mx-4 -mb-4 flex-1 overflow-y-auto">
                    @foreach($chatItems as $item)
                        @if($item['type'] === 'support')
                            {{-- Чат поддержки --}}
                            <button wire:click="selectSupportChat" @class([
                                'w-full px-4 py-3 flex items-center gap-3 text-left transition-colors cursor-pointer',
                                'hover:bg-gray-50 dark:hover:bg-white/5',
                                'bg-gray-100 dark:bg-white/5' => $supportChatSelected,
                            ])>
                                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                    style="background-color: #ffedd5; color: #ea580c">
                                    <x-heroicon-m-lifebuoy class="w-5 h-5" />
                                </div>

                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-950 dark:text-white truncate">
                                        Техническая поддержка
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                        @if($item['last_message_content'])
                                            {{ \Illuminate\Support\Str::limit($item['last_message_content'], 25) }}
                                        @else
                                            Напишите нам
                                        @endif
                                    </p>
                                </div>

                                @if($item['unread_count'] > 0)
                                    <x-filament::badge color="primary" size="sm">
                                        {{ $item['unread_count'] }}
                                    </x-filament::badge>
                                @endif
                            </button>
                        @else
                            {{-- Чат занятия --}}
                            @php $room = $item['room']; @endphp
                            <button wire:click="selectRoom({{ $room->id }})" @class([
                                'w-full px-4 py-3 flex items-center gap-3 text-left transition-colors cursor-pointer',
                                'hover:bg-gray-50 dark:hover:bg-white/5',
                                'bg-gray-100 dark:bg-white/5' => $selectedRoomId === $room->id && !$supportChatSelected,
                            ])>
                                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg"
                                    style="background-color: {{ $room->avatar_bg_color }}; color: {{ $room->avatar_text_color }}">
                                    {{ mb_substr($room->name, 0, 1) }}
                                </div>

                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-950 dark:text-white truncate">
                                        {{ $room->name }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $room->user->name }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                        @if($item['last_message_content'])
                                            {{ \Illuminate\Support\Str::limit($item['last_message_content'], 25) }}
                                        @else
                                            Нет сообщений
                                        @endif
                                    </p>
                                </div>

                                @if($item['unread_count'] > 0)
                                    <x-filament::badge color="primary" size="sm">
                                        {{ $item['unread_count'] }}
                                    </x-filament::badge>
                                @endif
                            </button>
                        @endif
                    @endforeach

                    @if($chatItems->isEmpty())
                        <div class="px-4 py-8 text-center">
                            <x-heroicon-o-inbox class="w-10 h-10 mx-auto text-gray-400 dark:text-gray-500" />
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Нет чатов</p>
                        </div>
                    @endif
                </div>
            </x-filament::section>
        </div>

        {{-- Область чата --}}
        <div class="flex-1 min-w-0 flex flex-col">
            @if($supportChatSelected)
                <livewire:support-chat-component :support-chat="$supportChat" :key="'support-chat-' . $supportChat->id" />
            @elseif($selectedRoom)
                <livewire:room-chat :room="$selectedRoom" :key="'chat-' . $selectedRoom->id" />
            @else
                <div
                    class="flex-1 flex items-center justify-center bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                    <div class="text-center">
                        <x-heroicon-o-chat-bubble-left-right class="mx-auto text-gray-400 dark:text-gray-500"
                            style="width: 64px; height: 64px;" />
                        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Выберите чат</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Чтобы начать переписку</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>