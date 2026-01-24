<div class="h-full min-h-0 flex flex-col" 
    x-data="{ 
        showUserCard: false, 
        isUploading: false, 
        progress: 0,
        optimisticMessages: [],
        currentUserAvatar: '{{ auth()->user()->avatar_url }}',
        submitMessage() {
            if (this.messageText.trim() === '' && !this.hasAttachments) return;

            // Optimistic UI for text messages without attachments
            if (this.messageText.trim() !== '' && !this.hasAttachments && !this.$wire.editingMessageId) {
                this.optimisticMessages.push({
                    id: 'opt-' + Date.now(),
                    content: this.messageText,
                    is_own: true,
                    user_avatar: this.currentUserAvatar,
                    created_at: new Date().toLocaleTimeString('ru-RU', {hour: '2-digit', minute:'2-digit', hour12: false}),
                    bg_color: '#ffedd5',
                    sending: true
                });
                
                const textToSend = this.messageText;
                this.messageText = ''; // Clear input immediately
                
                // Wait for DOM to render, then scroll smoothly
                this.$nextTick(() => {
                    requestAnimationFrame(() => {
                        const container = document.getElementById('support-messages-container');
                        if (container) {
                            container.scrollTop = container.scrollHeight;
                        }
                    });
                });
                
                this.$wire.sendMessage(textToSend).then(() => {
                     // Handled by message-sent
                });
            } else {
                this.$wire.call(this.$wire.editingMessageId ? 'updateMessage' : 'sendMessage');
            }
        }
    }" 
    x-on:livewire-upload-start="isUploading = true" 
    x-on:livewire-upload-finish="isUploading = false" 
    x-on:livewire-upload-error="isUploading = false" 
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    x-on:message-sent.window="optimisticMessages = []">
    @if($supportChat)
        <div class="flex-1 flex flex-col bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl overflow-hidden">
            {{-- Заголовок чата --}}
            <div class="px-6 py-4 border-b border-gray-200 dark:border-white/10 flex items-center gap-3 bg-white dark:bg-gray-900 z-10">
                <div class="md:hidden mr-1">
                    <button type="button" 
                        x-on:click="$dispatch('close-mobile-chat')"
                        class="p-2 -ml-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <x-heroicon-m-arrow-left class="w-6 h-6" />
                    </button>
                </div>
                @if($isAdmin)
                    {{-- Для админа показываем аватарку пользователя --}}
                    <x-filament::avatar :src="$supportChat->user->avatar_url" :alt="$supportChat->user->name" size="lg" />
                    <div>
                        <button @click="showUserCard = true"
                            class="inline-flex items-center gap-1 text-base font-semibold leading-6 text-gray-950 dark:text-white hover:text-primary-600 dark:hover:text-primary-400 transition-colors cursor-pointer">
                            {{ $supportChat->user->name }}
                            <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4 text-gray-400" />
                        </button>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $supportChat->user->display_role }}
                        </p>
                    </div>
                @else
                    {{-- Для пользователя показываем иконку поддержки --}}
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                        style="background-color: #ffedd5; color: #ea580c">
                        <x-heroicon-m-lifebuoy class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            Техническая поддержка
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Мы всегда на связи
                        </p>
                    </div>
                @endif
            </div>

            <style>
                @media (min-width: 768px) {
                    .chat-action-trigger {
                        opacity: 0 !important;
                    }

                    .message-row:hover .chat-action-trigger {
                        opacity: 1 !important;
                    }
                }

                .fi-dropdown-panel.chat-message-dropdown {
                    width: auto !important;
                    min-width: 15px !important;
                    max-width: max-content !important;
                    left: auto !important;
                    right: auto !important;
                    z-index: 100000 !important;
                }
            </style>

            {{-- Сообжения --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-4 relative"
                x-ref="chatContainer" id="support-messages-container"
                x-data="{ imageModal: false, imageUrl: '', isInitialized: false }" 
                x-init="$el.scrollTop = $el.scrollHeight; setTimeout(() => isInitialized = true, 500);"
                @message-sent.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                @message-received.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                
                @if($hasMorePages)
                    <div x-intersect="
                        if (isInitialized) {
                            $nextTick(() => {
                                const container = $refs.chatContainer;
                                const prevHeight = container.scrollHeight;
                                $wire.loadMore().then(() => {
                                    $nextTick(() => {
                                        container.scrollTop = container.scrollHeight - prevHeight;
                                    });
                                });
                            });
                        }
                    " class="py-4 flex justify-center">
                        <x-filament::loading-indicator class="w-6 h-6 text-gray-400" />
                    </div>
                @endif

                @forelse($messages as $message)
                    <div class="flex {{ $message['is_own'] ? 'justify-end' : 'justify-start' }} group/message message-row">
                        <div class="flex items-end gap-2 max-w-[75%] {{ $message['is_own'] ? 'flex-row-reverse' : '' }}" style="max-width: 100%; overflow: hidden;">
                            {{-- Avatar --}}
                            <div class="flex items-center shrink-0">
                                <x-filament::avatar :src="$message['user_avatar']" alt="{{ $message['user_name'] }}" size="md" />
                            </div>

                            {{-- Message bubble --}}
                            <div @class([
                                'min-w-0 rounded-xl px-4 py-2',
                                'text-gray-900 dark:text-white' => $message['is_own'],
                                'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100' => !$message['is_own'],
                            ]) style="{{ $message['is_own'] ? 'background-color: #ffedd5;' : '' }} max-width: 100%; overflow: hidden;">
                                @unless($message['is_own'])
                                    <p class="text-xs font-semibold mb-1" style="color: {{ $message['user_color'] }}">
                                        {{ $message['user_name'] }}
                                        @if($message['is_admin'])
                                            <span class="text-primary-500">(Поддержка)</span>
                                        @endif
                                    </p>
                                @endunless

                                {{-- Вложения --}}
                                @if(!empty($message['attachments']))
                                    <div class="mb-2 space-y-2 pt-2" style="max-width: 100%; overflow: hidden; width: 100%">
                                        @foreach($message['attachments'] as $attachment)
                                            @if(str_starts_with($attachment['type'], 'image/'))
                                                <button type="button" 
                                                    @click.prevent="imageUrl = '{{ Storage::disk('s3')->url($attachment['path']) }}'; imageModal = true" 
                                                    class="block text-left">
                                                    <img src="{{ Storage::disk('s3')->url($attachment['path']) }}"
                                                        alt="{{ $attachment['name'] }}"
                                                        loading="lazy"
                                                        class="max-w-full rounded-lg cursor-zoom-in hover:opacity-90 transition-opacity"
                                                        x-on:load="$nextTick(() => { const container = document.getElementById('support-messages-container'); if (container) container.scrollTop = container.scrollHeight; })"
                                                        style="max-height: 200px;" />
                                                </button>
                                            @else
                                                <div class="flex items-start gap-3 p-3 rounded-lg border border-gray-200/50 dark:border-white/10 {{ $message['is_own'] ? 'bg-white/60 dark:bg-black/20' : 'bg-white dark:bg-gray-900' }}" style="max-width: 100%; overflow: hidden; width: 100%; border: 1px solid #e5e7eb; background-color: {{ $message['is_own'] ? 'rgba(255, 255, 255, 0.6)' : '#ffffff' }};">
                                                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-gray-50 dark:bg-gray-800 flex items-center justify-center" style="border: 1px solid rgba(17, 24, 39, 0.05);">
                                                        <x-heroicon-o-document class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate" title="{{ $attachment['name'] }}">
                                                            {{ $attachment['name'] }}
                                                        </p>
                                                        <div class="flex items-center gap-2 mt-1 text-xs text-gray-500 dark:text-gray-400 flex-wrap">
                                                            <span>{{ isset($attachment['size']) ? number_format($attachment['size'] / 1024, 1) . ' КБ' : '' }}</span>
                                                            <span class="text-gray-300 dark:text-gray-600">•</span>
                                                            <a href="{{ Storage::disk('s3')->url($attachment['path']) }}" 
                                                               download="{{ $attachment['name'] }}"
                                                               class="text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 font-medium hover:underline transition-colors">
                                                                Скачать файл
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                @if($message['content'])
                                    <p class="text-sm whitespace-pre-wrap break-words" style="{{ !$message['is_own'] ? 'padding-top: 0.2rem;' : '' }}">{{ $message['content'] }}</p>
                                @endif
                                <p @class([
                                    'text-xs mt-1 text-right flex items-center justify-end gap-1',
                                    'text-gray-500 dark:text-gray-400' => $message['is_own'],
                                    'text-gray-400 dark:text-gray-500' => !$message['is_own'],
                                ])>
                                    <span>{{ $message['created_at'] }}</span>
                                    @if($message['is_own'])
                                        @if($message['read_at'])
                                            {{-- Double checkmark for read --}}
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M9.9101 3.53713C10.1659 3.21155 10.6371 3.15458 10.9628 3.41018C11.2884 3.666 11.3454 4.13722 11.0898 4.46292L5.58978 11.4639C5.45732 11.6324 5.25879 11.7362 5.04486 11.749C4.83078 11.7618 4.62132 11.6819 4.46967 11.5303L1.46967 8.5303C1.17678 8.2374 1.17678 7.76264 1.46967 7.46975C1.76256 7.17687 2.23732 7.17686 2.53021 7.46975L4.93256 9.87209L9.9101 3.53713Z" fill="#40B1E2"/>
                                                <path d="M13.9102 3.53713C14.166 3.21152 14.6372 3.15452 14.9629 3.41018C15.2885 3.66599 15.3454 4.13723 15.0899 4.46291L9.5899 11.4639C9.45745 11.6324 9.25888 11.7362 9.04497 11.749C8.83092 11.7618 8.62143 11.6819 8.46978 11.5303L7.46978 10.5303C7.17692 10.2374 7.17687 9.76262 7.46978 9.46975C7.76266 9.17692 8.23745 9.17692 8.53033 9.46975L8.93267 9.87209L13.9102 3.53713Z" fill="#40B1E2"/>
                                            </svg>
                                        @else
                                            {{-- Single checkmark for delivered --}}
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M11.9101 3.5371C12.1659 3.21151 12.6371 3.15455 12.9628 3.41015C13.2884 3.66597 13.3454 4.13719 13.0898 4.46288L7.58978 11.4639C7.45732 11.6323 7.25879 11.7362 7.04486 11.749C6.83078 11.7618 6.62132 11.6819 6.46967 11.5303L3.46967 8.53026C3.17678 8.23737 3.17678 7.76261 3.46967 7.46972C3.76256 7.17683 4.23732 7.17683 4.53021 7.46972L6.93256 9.87206L11.9101 3.5371Z" fill="#6B7280"/>
                                            </svg>
                                        @endif
                                    @endif
                                </p>
                            </div>

                            {{-- Action menu on opposite side --}}
                            @if(($message['can_delete'] ?? false) || ($message['can_edit'] ?? false))
                                <div class="flex items-center shrink-0">
                                    <x-filament::dropdown placement="top-end" :teleport="true" class="chat-message-dropdown">
                                        <x-slot name="trigger">
                                            <button class="p-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors chat-action-trigger opacity-0 group-hover/message:opacity-100">
                                                <x-heroicon-m-ellipsis-vertical class="w-4 h-4" />
                                            </button>
                                        </x-slot>
                                        
                                        <x-filament::dropdown.list>
                                            @if($message['can_edit'] ?? false)
                                                <x-filament::dropdown.list.item 
                                                    wire:click="editMessage({{ $message['id'] }})"
                                                    icon="heroicon-m-pencil" 
                                                    x-on:click="close">
                                                    Изменить
                                                </x-filament::dropdown.list.item>
                                            @endif

                                            @if($message['can_delete'] ?? false)
                                                <x-filament::dropdown.list.item
                                                    wire:click="deleteMessage({{ $message['id'] }})"
                                                    wire:confirm="Вы уверены, что хотите удалить это сообщение?"
                                                    icon="heroicon-m-trash" 
                                                    color="danger" 
                                                    x-on:click="close">
                                                    Удалить
                                                </x-filament::dropdown.list.item>
                                            @endif
                                        </x-filament::dropdown.list>
                                    </x-filament::dropdown>
                                </div>
                            @endif
                        </div>


                    </div>
                @empty
                    <div class="h-full flex items-center justify-center">
                        <div class="text-center">
                            <x-heroicon-o-chat-bubble-left-ellipsis class="mx-auto text-gray-400 dark:text-gray-500"
                                style="width: 64px; height: 64px;" />
                            <p class="mt-2 text-gray-500 dark:text-gray-400">Нет сообщений</p>
                            <p class="text-sm text-gray-400 dark:text-gray-500">
                                @if($isAdmin)
                                    Пользователь ещё не писал
                                @else
                                    Напишите нам, и мы поможем!
                                @endif
                            </p>
                        </div>
                    </div>
                @endforelse

                {{-- Optimistic Messages --}}
                <template x-for="msg in optimisticMessages" :key="msg.id">
                    <div class="flex justify-end group/message message-row mt-4">
                        <div class="flex items-end gap-2 max-w-[75%] flex-row-reverse" style="max-width: 100%; overflow: hidden;">
                            <div class="flex flex-col items-center gap-1 shrink-0">
                                <x-filament::avatar src="#" x-bind:src="currentUserAvatar" alt="Me" size="md" />
                            </div>

                            <div class="min-w-0 rounded-xl px-4 py-2 text-gray-900 dark:text-white" 
                                 style="background-color: #ffedd5; max-width: 100%; overflow: hidden; opacity: 0.7;">
                                <p class="text-sm whitespace-pre-wrap break-words" x-text="msg.content"></p>
                                <p class="text-xs mt-1 text-right text-gray-500 dark:text-gray-400 flex items-center justify-end gap-1">
                                    <span x-text="msg.created_at"></span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </p>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Lightbox Modal --}}
                <template x-teleport="body">
                    <div x-show="imageModal" 
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        @click="imageModal = false"
                        @keydown.escape.window="imageModal = false"
                        class="fixed inset-0 flex items-center justify-center"
                        style="z-index: 999999 !important; background-color: rgba(0, 0, 0, 0.9) !important;">
                        
                        <div class="relative w-full h-full flex items-center justify-center p-4">
                            {{-- Close button --}}
                            <button @click="imageModal = false" 
                                class="p-2 rounded-full bg-black/50 hover:bg-black/70 transition-colors"
                                style="position: absolute !important; top: 20px !important; right: 20px !important; z-index: 1000000 !important; color: white !important;">
                                <x-heroicon-o-x-mark class="w-8 h-8" />
                            </button>

                            {{-- Image --}}
                            <img :src="imageUrl" 
                                @click.stop
                                class="object-contain rounded-lg shadow-2xl" 
                                style="max-width: 90vw; max-height: 90vh;"
                                alt="Full size image">
                        </div>
                    </div>
                </template>
            </div>

            {{-- Форма отправки --}}
            <div class="p-4 border-t border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 z-10 sticky bottom-0">
                {{-- Превью прикрепленных файлов --}}
                @if(count($attachments) > 0)
                    <div class="mb-4 space-y-2">
                        @foreach($attachments as $index => $file)
                            <div class="flex items-center gap-3 p-3 bg-white dark:bg-gray-800 rounded-lg ring-1 ring-gray-200 dark:ring-gray-700">
                                @php
                                    $processed = $processedAttachments[$index] ?? null;
                                    $imageUrl = null;
                                    $isImage = false;

                                    if ($processed && isset($processed['path'])) {
                                        // If processed, rely on stored metadata
                                        $type = $processed['type'] ?? '';
                                        $isImage = str_starts_with($type, 'image/');
                                        if ($isImage) {
                                            $imageUrl = Storage::disk('s3')->url($processed['path']);
                                        }
                                    } else {
                                        // If not processed yet (or failed), try temporary file
                                        try {
                                             $type = $file->getMimeType();
                                             $isImage = str_starts_with($type, 'image/');
                                             if ($isImage && method_exists($file, 'temporaryUrl')) {
                                                 $imageUrl = $file->temporaryUrl();
                                             }
                                        } catch (\Exception $e) {
                                            $isImage = false;
                                        }
                                    }
                                @endphp

                                @if($isImage && $imageUrl)
                                    <img src="{{ $imageUrl }}" class="h-8 w-8 object-cover rounded flex-shrink-0" />
                                @else
                                    <div class="h-8 w-8 flex items-center justify-center rounded bg-gray-100 dark:bg-gray-700 flex-shrink-0">
                                        <x-heroicon-o-document class="w-4 h-4 text-gray-500" />
                                    </div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {{ $file->getClientOriginalName() }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        @if(isset($processedAttachments[$index]))
                                            {{ number_format($processedAttachments[$index]['size'] / 1024, 1) }} КБ
                                            @if($processedAttachments[$index]['processed'] ?? false)
                                                <span class="text-green-600 dark:text-green-400">• сжато</span>
                                            @endif
                                        @else
                                            <span class="inline-flex items-center gap-1">
                                                <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                обработка...
                                            </span>
                                        @endif
                                    </p>
                                </div>
                                <button type="button" wire:click="removeAttachment({{ $index }})"
                                    class="flex-shrink-0 p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                    title="Удалить">
                                    <x-heroicon-o-trash class="w-5 h-5" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($editingMessageId)
                    <div class="flex items-start justify-between mb-2 px-3 py-2 bg-gray-50 dark:bg-gray-800 rounded-lg border-l-4 border-primary-500">
                        <div class="flex-1 min-w-0 mr-2">
                            <p class="text-xs font-medium text-primary-600 dark:text-primary-400 mb-0.5">Редактирование сообщения</p>
                            <p class="text-xs text-gray-600 dark:text-gray-300 truncate">
                                {{ $editingMessageOriginalContent }}
                            </p>
                        </div>
                        <button wire:click="cancelEdit" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 shrink-0">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>
                @endif

                {{-- Индикатор загрузки (Skeleton) - показываем при клиентской загрузке --}}
                <div x-show="isUploading" x-cloak class="mb-4 w-full">
                    <div class="flex items-center gap-3 p-3 bg-white dark:bg-gray-800 rounded-lg ring-1 ring-gray-200 dark:ring-gray-700 animate-pulse">
                        <div class="h-10 w-10 bg-gray-200 dark:bg-gray-700 rounded flex-shrink-0"></div>
                        <div class="flex-1 min-w-0 space-y-2">
                            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
                            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                        </div>
                    </div>
                </div>

                {{-- Индикатор загрузки (Skeleton) - показываем при серверной обработке --}}
                <div wire:loading wire:target="attachments" class="mb-4 w-full">
                    <div class="flex items-center gap-3 p-3 bg-white dark:bg-gray-800 rounded-lg ring-1 ring-gray-200 dark:ring-gray-700 animate-pulse">
                        <div class="h-10 w-10 bg-gray-200 dark:bg-gray-700 rounded flex-shrink-0"></div>
                        <div class="flex-1 min-w-0 space-y-2">
                            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
                            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submitMessage" class="flex items-end gap-2" x-data="{
                    messageText: @entangle('newMessage'),
                    hasAttachments: {{ count($attachments) > 0 ? 'true' : 'false' }},
                    maxFileSize: 200 * 1024 * 1024, // 200 MB - должно совпадать с upload_max_filesize
                    maxFileSizeMB: 200,
                    maxFiles: 10,
                    validateFiles(event) {
                        const files = event.target.files;
                        if (files.length > this.maxFiles) {
                            alert(`Можно выбрать не более ${this.maxFiles} файлов за раз. Вы выбрали: ${files.length}`);
                            event.target.value = '';
                            return false;
                        }
                        const oversizedFiles = [];
                        for (let file of files) {
                            if (file.size > this.maxFileSize) {
                                oversizedFiles.push({
                                    name: file.name,
                                    size: (file.size / 1024 / 1024).toFixed(1)
                                });
                            }
                        }
                        if (oversizedFiles.length > 0) {
                            const fileList = oversizedFiles.map(f => `• ${f.name} (${f.size} МБ)`).join('\n');
                            alert(`Превышен максимальный размер файла (${this.maxFileSizeMB} МБ):\n\n${fileList}\n\nПожалуйста, выберите файлы меньшего размера или сожмите их.`);
                            event.target.value = '';
                            return false;
                        }
                        return true;
                    },
                    handlePaste(event) {
                        const items = (event.clipboardData || event.originalEvent.clipboardData).items;
                        const files = [];
                        
                        for (let index in items) {
                            const item = items[index];
                            if (item.kind === 'file' && item.type.indexOf('image/') !== -1) {
                                files.push(item.getAsFile());
                            }
                        }
                        
                        if (files.length > 0) {
                            // Создаем псевдо-объект события для валидации
                            const pseudoEvent = { target: { files: files, value: '' } };
                            
                            if (this.validateFiles(pseudoEvent)) {
                                isUploading = true;
                                $wire.uploadMultiple('attachments', files, 
                                    () => { isUploading = false; hasAttachments = true; }, 
                                    () => { isUploading = false; }, 
                                    (event) => { progress = event.detail.progress }
                                );
                            }
                        }
                    }
                }"
                x-on:focus-input.window="$nextTick(() => $refs.messageInput.focus())"
                >
                    {{-- Кнопка прикрепления файла --}}
                    <label class="cursor-pointer flex shrink-0 items-center justify-center w-[36px] h-[36px] rounded-lg text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors relative"
                        style="width: 36px; height: 36px;">
                        
                        <input type="file" x-ref="fileInput" class="hidden" multiple
                            accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar"
                            x-on:change="if(validateFiles($event)) { isUploading = true; $wire.uploadMultiple('attachments', $refs.fileInput.files, () => { isUploading = false; hasAttachments = true; }, () => { isUploading = false; }, (event) => { progress = event.detail.progress }) }" />
                        <x-heroicon-o-paper-clip class="w-5 h-5" />
                    </label>

                    <style>
                        #chat-message-input:focus {
                            outline: none !important;
                            --tw-ring-color: transparent !important;
                            border-color: #F97316 !important;
                            box-shadow: 0 0 0 1px #F97316 !important;
                        }
                    </style>
                    <textarea wire:model="newMessage" 
                        id="chat-message-input"
                        x-ref="messageInput"
                        x-effect="tmp='{{ rand() }}'; $wire.newMessage; $nextTick(() => { $el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'; $el.style.overflowY = ($el.scrollHeight > 100) ? 'auto' : 'hidden'; })"
                        x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'; $el.style.overflowY = ($el.scrollHeight > 100) ? 'auto' : 'hidden';"
                        class="flex-1 block w-full text-sm bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700 rounded-lg resize-none overflow-hidden focus:ring-0"
                        style="border: 1px solid #e5e7eb; min-height: 36px; max-height: 100px; padding-top: 7px !important; padding-bottom: 7px !important; line-height: 20px !important;"
                        rows="1" 
                        @keydown.enter.prevent="if(!$event.shiftKey) submitMessage()"
                        @paste="handlePaste($event)"></textarea>

                    <button type="submit" 
                        class="flex shrink-0 items-center justify-center rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-2"
                        :class="(messageText?.length > 0 || hasAttachments) ? 'hover:bg-primary-500 cursor-pointer' : 'cursor-default'"
                        :style="'width: 36px; height: 36px; transition: all 0.2s; background-color: ' + ((messageText?.length > 0 || hasAttachments) ? 'rgba(var(--primary-600),var(--tw-bg-opacity,1))' : '#E5E7EB') + '; color: ' + ((messageText?.length > 0 || hasAttachments) ? 'white' : '#9CA3AF')">
                        <x-heroicon-m-paper-airplane class="w-5 h-5" />
                    </button>
                </form>
            </div>
        </div>

        {{-- Модальное окно карточки пользователя (только для админа) --}}
        @if($isAdmin)
            {{-- Backdrop --}}
            <div x-show="showUserCard" x-cloak x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-40 bg-black/50"
                @click="showUserCard = false" @keydown.escape.window="showUserCard = false">
            </div>

            {{-- Modal --}}
            <div x-show="showUserCard" x-cloak x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 pointer-events-none">
                <div
                    class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto pointer-events-auto">

                    {{-- Header --}}
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            @if($supportChat->user->role === 'student')
                                Карточка ученика
                            @else
                                Карточка учителя
                            @endif
                        </h3>
                        <button @click="showUserCard = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    {{-- Content --}}
                    <div class="px-6 py-4 space-y-4">
                        {{-- Аватар и имя --}}
                        <div class="flex items-center gap-4">
                            <img src="{{ $supportChat->user->avatar_url }}" class="rounded-full object-cover shadow-md"
                                style="width: 80px; height: 80px;">
                            <div>
                                <p class="text-lg font-medium text-gray-900 dark:text-white">{{ $supportChat->user->name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $supportChat->user->display_role }}</p>
                            </div>
                        </div>

                        {{-- Email --}}
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</p>
                            <p class="mt-1 text-gray-900 dark:text-white break-all">{{ $supportChat->user->email }}</p>
                        </div>

                        {{-- Телефон --}}
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Телефон</p>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ $supportChat->user->phone ?? '-' }}</p>
                        </div>

                        {{-- Мессенджеры (для всех) --}}
                        @if($supportChat->user->telegram || $supportChat->user->whatsup)
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Мессенджеры</p>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    @if($supportChat->user->telegram)
                                        <a href="https://t.me/{{ $supportChat->user->telegram }}" target="_blank"
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400 rounded-md">
                                            Telegram: {{ $supportChat->user->telegram }}
                                        </a>
                                    @endif
                                    @if($supportChat->user->whatsup)
                                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $supportChat->user->whatsup) }}"
                                            target="_blank"
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400 rounded-md">
                                            WhatsApp: {{ $supportChat->user->whatsup }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Для учителей: предметы, классы, о себе --}}
                        @if(in_array($supportChat->user->role, ['mentor', 'tutor']))
                            {{-- Предметы --}}
                            @if($supportChat->user->subjects->isNotEmpty())
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Предметы</p>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach($supportChat->user->subjects as $subject)
                                            <span
                                                class="px-2 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-700 rounded-md text-gray-900 dark:text-white">{{ $subject->name }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Классы --}}
                            @if($supportChat->user->display_grade)
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Классы</p>
                                    <p class="mt-1 text-gray-900 dark:text-white">{{ $supportChat->user->display_grade }}</p>
                                </div>
                            @endif

                            {{-- О себе --}}
                            @if($supportChat->user->about)
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">О себе</p>
                                    <p class="mt-1 text-gray-900 dark:text-white whitespace-pre-wrap">{{ $supportChat->user->about }}
                                    </p>
                                </div>
                            @endif
                        @endif

                        {{-- Дата регистрации --}}
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Дата регистрации</p>
                            <p class="mt-1 text-gray-900 dark:text-white">
                                {{ $supportChat->user->created_at->format('d.m.Y H:i') }}
                            </p>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                        <x-filament::button @click="showUserCard = false" color="gray">
                            Закрыть
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif
    @else
    <div
        class="flex-1 flex items-center justify-center bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl">
        <div class="text-center">
            <x-heroicon-o-lifebuoy class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-500" />
            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Техническая поддержка</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Выберите чат для просмотра</p>
        </div>
    </div>
    @endif
</div>