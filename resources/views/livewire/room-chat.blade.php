<div wire:poll.30s="loadMessages" class="h-full flex flex-col">
    @if($room)
        <div
            class="flex-1 flex flex-col bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl overflow-hidden">
            {{-- Заголовок чата --}}
            <div
                class="px-6 py-4 border-b border-gray-200 dark:border-white/10 flex items-center gap-3 bg-white dark:bg-gray-900 z-10">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg"
                    style="background-color: {{ $room->avatar_bg_color }}; color: {{ $room->avatar_text_color }}">
                    {{ mb_substr($room->name, 0, 1) }}
                </div>
                <div x-data="{ showParticipants: false }">
                    <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        <a href="{{ \App\Filament\Student\Resources\RoomResource::getUrl('view', ['record' => $room]) }}"
                            class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors inline-flex items-center gap-1.5">
                            {{ $room->name }}
                            <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                        </a>
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        @if($room->user_id !== auth()->id())
                            {{ $room->user->name }}
                            @if($room->participants->count() > 0)
                                ·
                            @endif
                        @endif
                        @if($room->participants->count() > 0)
                            <button @click="showParticipants = true"
                                class="underline hover:text-primary-500 transition-colors cursor-pointer">
                                {{ $room->participants->count() }}
                                {{ trans_choice('{1} участник|[2,4] участника|[5,*] участников', $room->participants->count()) }}
                            </button>
                        @endif
                    </p>

                    {{-- Модальное окно со списком участников --}}
                    <template x-teleport="body">
                        <div x-show="showParticipants" x-cloak x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="fixed inset-0 flex items-center justify-center bg-black/50"
                            style="z-index: 999999 !important;" @click.self="showParticipants = false"
                            @keydown.escape.window="showParticipants = false">
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full mx-4 max-h-[80vh] overflow-hidden"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95">
                                <div
                                    class="px-6 py-4 border-b border-gray-200 dark:border-white/10 flex items-center justify-between">
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        Участники ({{ $room->participants->count() }})
                                    </h4>
                                    <button @click="showParticipants = false"
                                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                        <x-heroicon-o-x-mark class="w-5 h-5" />
                                    </button>
                                </div>
                                <div class="p-4 space-y-3 overflow-y-auto max-h-[60vh]">
                                    @foreach($room->participants as $participant)
                                        <div class="flex items-center gap-3">
                                            <x-filament::avatar :src="$participant->avatar_url" :alt="$participant->name"
                                                size="md" />
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">{{ $participant->name }}
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $participant->email }}
                                                </p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Сообщения --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-4" id="messages-container" x-data
                x-init="$el.scrollTop = $el.scrollHeight"
                @message-sent.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                @message-received.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                @forelse($messages as $message)
                    <div class="flex {{ $message['is_own'] ? 'justify-end' : 'justify-start' }}">
                        <div class="flex items-end gap-2 max-w-[75%] {{ $message['is_own'] ? 'flex-row-reverse' : '' }}">
                            <x-filament::avatar :src="$message['user_avatar']" alt="{{ $message['user_name'] }}" size="md" />

                            <div @class([
                                'rounded-xl px-4 py-2',
                                'bg-primary-600 text-white' => $message['is_own'],
                                'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white' => !$message['is_own'],
                            ])>
                                @unless($message['is_own'])
                                    <p class="text-xs font-semibold mb-1" style="color: {{ $message['user_color'] }}">
                                        {{ $message['user_name'] }}
                                    </p>
                                @endunless
                                <p class="text-sm whitespace-pre-wrap break-words">{{ $message['content'] }}</p>
                                <p @class([
                                    'text-xs mt-1 text-right',
                                    'text-white/80' => $message['is_own'],
                                    'text-gray-400 dark:text-gray-500' => !$message['is_own'],
                                ])>
                                    {{ $message['created_at'] }}
                                </p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="h-full flex items-center justify-center">
                        <div class="text-center">
                            <x-heroicon-o-chat-bubble-left-ellipsis class="mx-auto text-gray-400 dark:text-gray-500"
                                style="width: 64px; height: 64px;" />
                            <p class="mt-2 text-gray-500 dark:text-gray-400">Нет сообщений</p>
                            <p class="text-sm text-gray-400 dark:text-gray-500">Напишите первое сообщение!</p>
                        </div>
                    </div>
                @endforelse
            </div>

            {{-- Форма отправки --}}
            <div class="p-4 border-t border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 z-10 sticky bottom-0">
                <form wire:submit="sendMessage" class="flex gap-2">
                    <x-filament::input.wrapper class="flex-1">
                        <x-filament::input type="text" wire:model="newMessage" placeholder="Введите сообщение..."
                            autocomplete="off" />
                    </x-filament::input.wrapper>

                    <x-filament::button type="submit" icon="heroicon-m-paper-airplane">
                        Отправить
                    </x-filament::button>
                </form>
            </div>
        </div>
    @else
        <div
            class="flex-1 flex items-center justify-center bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl">
            <div class="text-center">
                <x-heroicon-o-chat-bubble-left-right class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-500" />
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Выберите занятие</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Чтобы начать переписку</p>
            </div>
        </div>
    @endif
</div>