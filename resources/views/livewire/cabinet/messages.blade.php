{{-- Сообщения: слева диалоги (поддержка закреплена), справа переписка. На телефоне переписка открывается поверх списка. --}}
@php
    $row = fn (array $d) => [
        'flex w-full items-center gap-3 rounded p-3 text-left',
        'bg-mint' => $current && $current['key'] === $d['key'],
        'hover:bg-soft-hover' => ! $current || $current['key'] !== $d['key'],
    ];
@endphp
<div class="flex min-h-0 flex-1">

    {{-- Диалоги --}}
    <section class="flex w-full flex-col lg:w-dialogs lg:shrink-0 lg:border-r lg:border-line" aria-labelledby="messages-title">
        <div class="flex flex-col gap-6 px-4 pb-4 pt-6 lg:px-6 lg:pt-12">
            <h1 id="messages-title" class="text-h1-m font-semibold lg:text-h1">Сообщения</h1>
            <x-ui.search full wire:model.live.debounce.300ms="q" :placeholder="\App\Services\MessengerService::isTeacher(auth()->user()) ? 'Найти ученика или группу' : 'Найти учителя или группу'" />
        </div>

        <div class="flex flex-col gap-1 px-3 pb-tabbar lg:min-h-0 lg:flex-1 lg:overflow-y-auto lg:pb-6">
            @if ($supportDialog)
                {{-- Чат поддержки подсвечивает тур по кабинету --}}
                <div data-tour="support-chat" class="flex flex-col">
                    @include('livewire.cabinet.messages.dialog', ['d' => $supportDialog, 'classes' => $row($supportDialog)])
                </div>
            @endif
            @if ($supportDialog && $dialogs->isNotEmpty())
                <div class="mx-3 my-1 h-px shrink-0 bg-line"></div>
            @endif
            @foreach ($dialogs as $d)
                @include('livewire.cabinet.messages.dialog', ['d' => $d, 'classes' => $row($d)])
            @endforeach

            @if (! $supportDialog && $dialogs->isEmpty())
                <p class="p-3 text-t2 text-muted">Никого не нашли — проверьте имя или название группы</p>
            @elseif ($q === '' && $dialogs->isEmpty())
                <p class="p-3 text-t2 text-muted">
                    {{ \App\Services\MessengerService::isTeacher(auth()->user()) ? 'Чатов пока нет. Написать ученику можно из его карточки.' : 'Чат появится, когда у вас будет учитель.' }}
                </p>
            @endif
        </div>
    </section>

    {{-- Переписка --}}
    @if ($current)
        <section class="fixed inset-0 z-20 flex min-w-0 flex-col bg-white lg:static lg:z-auto lg:flex-1" aria-label="{{ $current['name'] }}" wire:key="chat-{{ $current['key'] }}">
            <header class="flex items-center gap-3 border-b border-line px-4 py-3 lg:px-8 lg:py-6">
                <x-ui.btn square icon="arrow-left" wire:click="close" aria-label="К списку диалогов" class="lg:hidden" />
                @include('livewire.cabinet.messages.avatar', ['d' => $current])
                <div class="flex min-w-0 flex-1 flex-col">
                    <span class="truncate text-t1 font-medium">{{ $current['name'] }}</span>
                    @if ($current['sub'])<span class="truncate text-t2 text-muted">{{ $current['sub'] }}</span>@endif
                </div>
                @if ($current['link'])
                    <a href="{{ $current['link']['url'] }}" class="link hidden shrink-0 text-t2 lg:inline">{{ $current['link']['label'] }}</a>
                @endif
            </header>

            <div class="flex min-h-0 flex-1 flex-col-reverse overflow-y-auto px-4 py-6 lg:px-8">
                <div class="flex flex-col gap-2">
                    @if ($thread['more'])
                        <button type="button" wire:click="more" class="link self-center text-t2">Показать ранние сообщения</button>
                    @endif

                    @forelse ($thread['items'] as $m)
                        @if (isset($m['day']))
                            <x-ui.badge class="mb-2 mt-4 self-center">{{ $m['day'] }}</x-ui.badge>
                        @else
                            <div @class(['group flex max-w-full items-start gap-1', 'flex-row-reverse self-end' => $m['own'], 'self-start' => ! $m['own']]) wire:key="m-{{ $m['id'] }}">
                                <div @class([
                                    'flex min-w-0 max-w-bubble flex-col gap-2 rounded-lg px-4 py-3',
                                    'bg-mint' => $m['own'],
                                    'border border-line bg-white' => ! $m['own'],
                                ])>
                                    @if ($m['who'])<span class="text-t3 font-semibold">{{ $m['who'] }}</span>@endif

                                    @foreach ($m['files'] as $f)
                                        @if ($f['image'])
                                            <a href="{{ $f['url'] }}" target="_blank" rel="noopener" class="block" data-lightbox data-name="{{ $f['name'] }}">
                                                <img src="{{ $f['url'] }}" alt="{{ $f['name'] }}" class="max-h-44 max-w-full rounded" loading="lazy">
                                            </a>
                                        @else
                                            <a href="{{ $f['url'] }}" download="{{ $f['name'] }}" target="_blank" rel="noopener" class="flex min-w-0 items-center gap-3">
                                                <x-ui.file-tile :name="$f['name']" :on-mint="$m['own']" />
                                                <span class="flex min-w-0 flex-1 flex-col">
                                                    <span class="truncate text-t1-s font-medium">{{ $f['name'] }}</span>
                                                    @if ($f['size'])<span class="text-t2 text-muted">{{ $f['size'] }}</span>@endif
                                                </span>
                                                <x-ui.icon name="download" size="s" class="text-muted" />
                                            </a>
                                        @endif
                                    @endforeach

                                    @if ($m['text'] !== '')<p class="whitespace-pre-wrap break-words text-t1-s">{{ \App\Support\RichText::linkify($m['text']) }}</p>@endif

                                    <span class="flex items-center justify-end gap-1 text-count text-muted">
                                        {{ $m['time'] }}
                                        @if ($m['own'])
                                            <x-ui.icon :name="$m['read'] ? 'check-double' : 'check'" size="s" />
                                            <span class="sr-only">{{ $m['read'] ? 'прочитано' : 'отправлено' }}</span>
                                        @endif
                                    </span>
                                </div>

                                @if ($m['canEdit'] || $m['canDelete'])
                                    <x-ui.menu label="Действия с сообщением" :align="$m['own'] ? 'right' : 'left'" class="lg:opacity-0 lg:focus-within:opacity-100 lg:group-hover:opacity-100">
                                        @if ($m['canEdit'])<x-ui.menu-item wire:click="edit({{ $m['id'] }})">Изменить</x-ui.menu-item>@endif
                                        @if ($m['canDelete'])<x-ui.menu-item wire:click="confirmDelete({{ $m['id'] }})">Удалить</x-ui.menu-item>@endif
                                    </x-ui.menu>
                                @endif
                            </div>
                        @endif
                    @empty
                        <p class="self-center py-12 text-center text-t2 text-muted">
                            {{ $current['type'] === 'support' ? 'Напишите вопрос — ответим в течение дня.' : 'Сообщений пока нет. Напишите первым.' }}
                        </p>
                    @endforelse
                </div>
            </div>

            @if ($canWrite)
                <form wire:submit="send" class="flex flex-col gap-2 border-t border-line px-4 pb-4 pt-3 lg:px-8 lg:pb-6 lg:pt-4">
                    @if ($editingId)
                        <div class="flex items-center gap-2 text-t2">
                            <span class="text-muted">Изменение сообщения</span>
                            <button type="button" wire:click="cancelEdit" class="link">Отменить</button>
                        </div>
                    @endif

                    @if ($files)
                        <div class="flex flex-wrap gap-2">
                            @foreach ($files as $i => $f)
                                <span class="flex min-w-0 items-center gap-2 rounded bg-soft py-1 pl-3 pr-1 text-t2" wire:key="file-{{ $i }}-{{ $f['path'] }}">
                                    <span class="truncate">{{ $f['name'] }}</span>
                                    <button type="button" wire:click="removeFile({{ $i }})" class="flex size-6 shrink-0 items-center justify-center rounded-sm text-muted hover:text-ink" aria-label="Убрать {{ $f['name'] }}"><x-ui.icon name="x" size="s" /></button>
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @error('picked')<p class="text-t2 text-danger-fg">{{ $message }}</p>@enderror
                    @error('picked.*')<p class="text-t2 text-danger-fg">{{ $message }}</p>@enderror
                    @error('draft')<p class="text-t2 text-danger-fg">{{ $message }}</p>@enderror

                    <div class="flex items-end gap-2">
                        @unless ($editingId)
                            <label class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded text-muted hover:bg-soft-hover hover:text-ink">
                                <span class="sr-only">Прикрепить файл</span>
                                <input type="file" multiple wire:model="picked" class="sr-only">
                                <x-ui.icon name="attach" />
                            </label>
                        @endunless
                        <label class="sr-only" for="message-draft">Сообщение</label>
                        <textarea id="message-draft" rows="1" wire:model="draft" placeholder="Напишите сообщение"
                            x-data="{ fit() { $el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 160) + 'px' } }"
                            x-init="fit()" x-on:input="fit()"
                            x-on:chat-edit.window="$nextTick(() => { fit(); $el.focus() })"
                            x-on:chat-opened.window="$nextTick(() => $el.focus())"
                            x-on:keydown.enter="if (! $event.shiftKey && ! $event.isComposing) { $event.preventDefault(); $wire.send().then(() => $nextTick(() => fit())) }"
                            class="min-h-11 flex-1 resize-none rounded bg-white px-4 py-2 text-t1 text-ink shadow-outline outline-none placeholder:text-faint focus:shadow-outline-ink"></textarea>
                        <x-ui.btn type="submit" variant="dark" square :icon="$editingId ? 'check' : 'send'" :aria-label="$editingId ? 'Сохранить' : 'Отправить'" wire:loading.attr="disabled" wire:target="send,picked" />
                    </div>
                </form>
            @else
                <div class="flex items-center justify-center gap-2 border-t border-line px-8 py-6 text-t2 text-muted">
                    <x-ui.icon name="lock" size="s" />Занятие в архиве — писать сюда больше нельзя
                </div>
            @endif
        </section>
    @else
        <section class="hidden flex-1 items-center justify-center lg:flex">
            <x-ui.empty icon="chat" title="Выберите диалог" />
        </section>
    @endif

    @if ($deletingId)
        <x-ui.modal title="Удалить сообщение?" close="cancelDelete" width="s">
            <p class="text-t1">Сообщение пропадёт у всех участников чата вместе с файлами.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="cancelDelete">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="delete">Удалить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
