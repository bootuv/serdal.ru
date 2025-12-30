<x-filament-panels::page>
    <div class="flex flex-col md:flex-row gap-6" style="height: calc(100vh - 10rem);">
        {{-- Список чатов поддержки --}}
        <div class="md:flex-shrink-0 flex flex-col" style="min-width: 400px;">
            <x-filament::section class="flex-1 flex flex-col">
                <x-slot name="heading">
                    Чаты поддержки
                </x-slot>

                <div class="space-y-2 -mx-4 -mb-4 flex-1 overflow-y-auto">
                    @forelse($chats as $chat)
                        <button wire:click="selectChat({{ $chat->id }})" @class([
                            'w-full px-4 py-3 flex items-center gap-3 text-left transition-colors cursor-pointer',
                            'hover:bg-gray-50 dark:hover:bg-white/5',
                            'bg-gray-100 dark:bg-white/5' => $selectedChatId === $chat->id,
                        ])>
                            <x-filament::avatar :src="$chat->user->avatar_url" :alt="$chat->user->name" size="lg" />

                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-950 dark:text-white truncate">
                                    {{ $chat->user->name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $chat->user->display_role }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                    @if($chat->messages->isNotEmpty())
                                        {{ \Illuminate\Support\Str::limit($chat->messages->first()->content, 25) }}
                                    @else
                                        Нет сообщений
                                    @endif
                                </p>
                            </div>

                            @if($chat->unread_messages_count > 0)
                                <x-filament::badge color="primary" size="sm">
                                    {{ $chat->unread_messages_count }}
                                </x-filament::badge>
                            @endif
                        </button>
                    @empty
                        <div class="px-4 py-8 text-center">
                            <x-heroicon-o-inbox class="w-10 h-10 mx-auto text-gray-400 dark:text-gray-500" />
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Нет обращений</p>
                        </div>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        {{-- Область чата --}}
        <div class="flex-1 min-w-0 flex flex-col">
            @if($selectedChat)
                <livewire:support-chat-component :support-chat="$selectedChat" :key="'support-chat-' . $selectedChat->id" />
            @else
                <div
                    class="flex-1 flex items-center justify-center bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                    <div class="text-center">
                        <x-heroicon-o-lifebuoy class="mx-auto text-gray-400 dark:text-gray-500"
                            style="width: 64px; height: 64px;" />
                        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Выберите чат</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Чтобы ответить на обращение</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>