{{-- Поддержка (админка). Макет: AdminSupport. Слева обращения (поиск, «Непрочитанные / Все»), справа переписка,
     «Карточка человека» — боковая панель. На телефоне переписка открывается поверх списка. --}}
<div class="flex min-h-0 flex-1">

    {{-- Обращения --}}
    <section class="flex w-full flex-col lg:w-dialogs lg:shrink-0 lg:border-r lg:border-line" aria-labelledby="support-title">
        <div class="flex flex-col gap-6 px-4 pb-4 pt-6 lg:px-6 lg:pt-12">
            <h1 id="support-title" class="text-h1-m font-medium lg:text-h1">Поддержка</h1>
            <x-ui.search full wire:model.live.debounce.300ms="q" placeholder="Имя или почта" />
            <x-ui.seg fit model="filter" :active="$filter" :items="$filters" />
        </div>

        <div class="flex flex-col gap-1 px-3 pb-tabbar lg:min-h-0 lg:flex-1 lg:overflow-y-auto lg:pb-6">
            @foreach ($dialogs as $d)
                <button type="button" wire:click="open({{ $d['id'] }})" wire:key="dialog-{{ $d['id'] }}" aria-current="{{ $current && $current['id'] === $d['id'] ? 'true' : 'false' }}"
                        @class(['flex w-full items-center gap-3 rounded p-3 text-left', 'bg-mint' => $current && $current['id'] === $d['id'], 'hover:bg-soft-hover' => ! $current || $current['id'] !== $d['id']])>
                    <x-ui.avatar :user="$d['user']" :name="$d['name']" />
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="flex min-w-0 items-center gap-2">
                            <span @class(['truncate text-t1-s', 'font-semibold' => $d['unread'], 'font-medium' => ! $d['unread']])>{{ $d['name'] }}</span>
                            <span class="shrink-0 text-t3 text-muted">{{ $d['role'] }}</span>
                            <span class="ml-auto shrink-0 text-t3 text-muted">{{ $d['time'] }}</span>
                        </span>
                        <span class="flex min-w-0 items-center gap-2">
                            <span @class(['flex-1 truncate text-t2', 'text-ink' => $d['unread'], 'text-muted' => ! $d['unread']])>{{ $d['preview'] }}</span>
                            <x-ui.count :value="$d['unread']" />
                        </span>
                    </span>
                </button>
            @endforeach

            @if ($nothingFound)
                <p class="p-3 text-t2 text-muted">Никого не нашли — проверьте имя или почту</p>
            @elseif ($allRead)
                <p class="p-3 text-t2 text-muted">Непрочитанных нет — все обращения разобраны</p>
            @elseif ($dialogs->isEmpty())
                <p class="p-3 text-t2 text-muted">Обращений пока нет — они появятся, когда кто-то напишет в поддержку</p>
            @endif
        </div>
    </section>

    {{-- Переписка --}}
    @if ($current)
        <section class="fixed inset-0 z-20 flex min-w-0 flex-col bg-white lg:static lg:z-auto lg:flex-1" aria-label="{{ $current['name'] }}" wire:key="chat-{{ $current['id'] }}">
            <header class="flex items-center gap-3 border-b border-line px-4 py-3 lg:px-8 lg:py-6">
                <x-ui.btn square icon="arrow-left" wire:click="close" aria-label="К списку обращений" class="lg:hidden" />
                <x-ui.avatar :user="$current['user']" />
                <div class="flex min-w-0 flex-1 flex-col">
                    <span class="truncate text-t1 font-medium">{{ $current['name'] }}</span>
                    <span class="truncate text-t2 text-muted">{{ $current['sub'] }}</span>
                </div>
                <x-ui.btn size="s" icon="user" wire:click="openCard" class="hidden lg:inline-flex">Карточка человека</x-ui.btn>
                <x-ui.btn size="s" square icon="user" wire:click="openCard" aria-label="Карточка человека" class="lg:hidden" />
            </header>

            <div class="flex min-h-0 flex-1 flex-col-reverse overflow-y-auto px-4 py-6 lg:px-8">
                <div class="flex flex-col gap-2">
                    @if ($thread['more'])
                        <button type="button" wire:click="more" class="link self-center text-t2">Показать ранние сообщения</button>
                    @endif

                    @foreach ($thread['items'] as $m)
                        @if (isset($m['day']))
                            <x-ui.badge class="mb-2 mt-4 self-center">{{ $m['day'] }}</x-ui.badge>
                        @else
                            <div @class(['group flex max-w-full items-start gap-1', 'flex-row-reverse self-end' => $m['own'], 'self-start' => ! $m['own']]) wire:key="m-{{ $m['id'] }}">
                                <div @class(['flex min-w-0 max-w-bubble flex-col gap-2 rounded-lg px-4 py-3', 'bg-mint' => $m['own'], 'border border-line bg-white' => ! $m['own']])>
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

                                @if ($m['own'] && ! $m['who'])
                                    <x-ui.menu label="Действия с сообщением" align="right" class="lg:opacity-0 lg:focus-within:opacity-100 lg:group-hover:opacity-100">
                                        @if ($m['canEdit'])<x-ui.menu-item wire:click="edit({{ $m['id'] }})">Изменить</x-ui.menu-item>@endif
                                        <x-ui.menu-item wire:click="confirmDelete({{ $m['id'] }})">Удалить</x-ui.menu-item>
                                    </x-ui.menu>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

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
                    <label class="sr-only" for="support-draft">Ответ</label>
                    <textarea id="support-draft" rows="1" wire:model="draft" placeholder="Напишите ответ"
                        x-data="{ fit() { $el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 160) + 'px' } }"
                        x-init="fit()" x-on:input="fit()"
                        x-on:chat-edit.window="$nextTick(() => { fit(); $el.focus() })"
                        x-on:chat-opened.window="$nextTick(() => $el.focus())"
                        x-on:keydown.enter="if (! $event.shiftKey && ! $event.isComposing) { $event.preventDefault(); $wire.send().then(() => $nextTick(() => fit())) }"
                        class="min-h-11 flex-1 resize-none rounded bg-white px-4 py-2 text-t1 text-ink shadow-outline outline-none placeholder:text-faint focus:shadow-outline-ink"></textarea>
                    <x-ui.btn type="submit" variant="dark" square :icon="$editingId ? 'check' : 'send'" :aria-label="$editingId ? 'Сохранить' : 'Отправить'" wire:loading.attr="disabled" wire:target="send,picked" />
                </div>
            </form>
        </section>
    @else
        <section class="hidden flex-1 items-center justify-center lg:flex">
            <x-ui.empty icon="chat" title="Выберите обращение" />
        </section>
    @endif

    {{-- Карточка человека --}}
    @if ($card)
        <div class="fixed inset-0 z-30 flex justify-end bg-scrim" x-data x-on:keydown.escape.window="$wire.closeCard()" wire:click.self="closeCard">
            <aside role="dialog" aria-modal="true" aria-labelledby="card-title" class="flex h-full w-full flex-col bg-white shadow-modal lg:w-drawer lg:rounded-l-xl">
                <div class="flex items-center justify-between gap-4 px-6 pb-4 pt-6">
                    <div class="flex min-w-0 items-center gap-4">
                        <x-ui.avatar :user="$current['user']" size="lg" />
                        <div class="flex min-w-0 flex-col gap-1">
                            <h2 id="card-title" class="truncate text-h2 font-medium">{{ $card['name'] }}</h2>
                            <span class="text-t2 text-muted">{{ $card['role'] }}</span>
                        </div>
                    </div>
                    <x-ui.btn square icon="x" wire:click="closeCard" aria-label="Закрыть" />
                </div>

                <div class="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto px-6 pb-6 pt-2">
                    <section class="flex flex-col gap-3" aria-labelledby="card-contacts">
                        <span id="card-contacts" class="text-t3 font-semibold text-muted">Контакты</span>
                        <div class="flex flex-col gap-3 text-t1-s">
                            @if ($card['email'])
                                <a href="mailto:{{ $card['email'] }}" class="flex min-w-0 items-center gap-3"><x-ui.icon name="mail" size="s" class="text-muted" /><span class="truncate">{{ $card['email'] }}</span></a>
                            @endif
                            @if ($card['phone'])
                                <a href="tel:{{ $card['phone'] }}" class="flex items-center gap-3"><x-ui.icon name="chat" size="s" class="text-muted" /><span>{{ $card['phone'] }}</span></a>
                            @endif
                            @if ($card['telegram'])
                                <a href="{{ $card['telegramUrl'] }}" target="_blank" rel="noopener" class="flex items-center gap-3"><x-ui.icon name="send" size="s" class="text-muted" /><span>Telegram · {{ $card['telegram'] }}</span></a>
                            @endif
                            @if ($card['whatsapp'])
                                <a href="{{ $card['whatsappUrl'] }}" target="_blank" rel="noopener" class="flex items-center gap-3"><x-ui.icon name="chat" size="s" class="text-muted" /><span>WhatsApp · {{ $card['whatsapp'] }}</span></a>
                            @endif
                        </div>
                    </section>

                    @if ($card['rows'])
                        <div class="h-px bg-line"></div>
                        <section class="flex flex-col gap-3" aria-labelledby="card-about">
                            <span id="card-about" class="text-t3 font-semibold text-muted">На Serdal</span>
                            <dl class="grid grid-cols-3 gap-x-4 gap-y-3 text-t1-s">
                                @foreach ($card['rows'] as $r)
                                        <dt class="text-muted">{{ $r['k'] }}</dt>
                                        <dd class="col-span-2 flex min-w-0 flex-col gap-1">
                                            <span>{{ $r['v'] }}</span>
                                            @if ($r['note'] || $r['em'])
                                                <span class="text-t2 text-muted">{{ $r['note'] }}@if ($r['em']) <x-ui.em>{{ $r['em'] }}</x-ui.em>@endif</span>
                                            @endif
                                        </dd>
                                @endforeach
                            </dl>
                        </section>
                    @endif
                </div>

                @if ($card['url'])
                    <div class="flex items-center justify-between gap-4 border-t border-line px-6 py-4">
                        <a href="{{ $card['url'] }}" class="link text-t1-s">Открыть в пользователях</a>
                    </div>
                @endif
            </aside>
        </div>
    @endif

    @if ($deletingId)
        <x-ui.modal title="Удалить сообщение?" close="cancelDelete" width="s">
            <p class="text-t1">Сообщение пропадёт и у пользователя вместе с файлами.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="cancelDelete">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="delete">Удалить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
