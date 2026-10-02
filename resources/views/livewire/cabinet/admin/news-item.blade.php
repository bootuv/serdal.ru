{{-- Новость: как статья блога — чистый лист посередине (заголовок + блочный редактор, без рамки), настройки — в панели справа.
     Панель открывается кнопкой «Настройки» и сама — при ошибке в ее полях (событие news-settings). --}}
<div class="flex flex-col gap-8 lg:gap-12" x-data="{ settings: false }" x-on:news-settings.window="settings = true">
    <div class="flex flex-wrap items-center justify-between gap-4" {!! in_array($status, ['new', 'draft'], true) ? 'wire:poll.5s.visible="autosave"' : '' !!}>
        <a href="{{ route('cabinet.admin.news') }}" class="inline-flex items-center gap-2 text-t1-s font-medium text-muted hover:text-ink"><x-ui.icon name="arrow-left" size="s" />Новости</a>
        <div class="flex flex-wrap items-center gap-4">
            {{-- Автосохранение черновика: что с текстом сейчас --}}
            <span class="text-t2 text-muted" wire:loading.remove wire:target="autosave">
                @if ($savedAt)
                    Сохранено в {{ $savedAt }}
                @elseif ($facts)
                    <span class="hidden xl:inline">{{ $facts }}</span>
                @endif
            </span>
            <span class="text-t2 text-muted" wire:loading wire:target="autosave">Сохраняем…</span>

            <div class="flex items-center gap-2">
                <x-ui.btn icon="settings" x-on:click="settings = true">Настройки</x-ui.btn>

                {{-- Главная кнопка со стрелкой: остальные действия публикации — в списке под ней --}}
                <div class="relative flex" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                    <x-ui.btn variant="primary" class="rounded-r-none" wire:click="submit" wire:loading.attr="disabled" wire:target="saveDraft,submit,publishNow">{{ $submitLabel }}</x-ui.btn>
                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="menu" aria-label="Другие действия"
                            class="flex h-11 w-9 items-center justify-center rounded-r border-l border-line-strong bg-brand text-ink hover:bg-brand-hover">
                        <x-ui.icon name="chevron-down" size="s" class="transition-transform" x-bind:class="open && 'rotate-180'" />
                    </button>
                    <div x-show="open" x-cloak x-on:click="open = false" role="menu"
                         class="absolute right-0 top-full z-20 mt-1 flex w-sidebar flex-col rounded border border-line bg-white p-1 shadow-card">
                        @if ($status !== 'published')
                            <x-ui.menu-item wire:click="publishNow">Опубликовать сейчас</x-ui.menu-item>
                            <x-ui.menu-item wire:click="planLater">{{ $status === 'scheduled' ? 'Изменить время публикации' : 'Запланировать…' }}</x-ui.menu-item>
                        @endif
                        @if (in_array($status, ['new', 'draft'], true))
                            <x-ui.menu-item wire:click="saveDraft">Сохранить как черновик</x-ui.menu-item>
                        @else
                            <x-ui.menu-item wire:click="unpublish">{{ $status === 'published' ? 'Снять с публикации' : 'Вернуть в черновики' }}</x-ui.menu-item>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <article class="mx-auto flex w-full max-w-form flex-col gap-4 pb-12" aria-label="Текст новости">
        <label class="flex flex-col gap-2">
            <span class="sr-only">Заголовок</span>
            <textarea name="title" rows="1" wire:model.live.debounce.500ms="title" placeholder="Заголовок"
                      x-data x-init="$nextTick(() => { $el.style.height = $el.scrollHeight + 'px' })" x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                      class="w-full resize-none overflow-hidden bg-transparent text-h1-m font-medium text-ink outline-none placeholder:text-faint lg:text-h1"></textarea>
            @error('title')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
        </label>
        <x-ui.block-editor label="Текст новости" name="body" wire:model="body" upload-model="image" upload-method="storeImage" video-model="video" video-method="storeVideo"
                           hint="Картинки, GIF и видео сжимаются сами. Видео — до 200 МБ" />
    </article>

    {{-- Настройки новости --}}
    <div x-show="settings" x-cloak class="fixed inset-0 z-30 flex justify-end bg-scrim"
         x-on:keydown.escape.window="settings = false" x-on:click.self="settings = false"
         x-transition:enter="transition-opacity" x-transition:enter-start="opacity-0" x-transition:leave="transition-opacity" x-transition:leave-end="opacity-0">
        <aside role="dialog" aria-modal="true" aria-labelledby="an-settings" class="flex h-full w-full flex-col bg-white shadow-modal lg:w-drawer lg:rounded-l-xl">
            <div class="flex items-center justify-between gap-4 px-6 pb-4 pt-6">
                <div class="flex min-w-0 flex-col gap-1">
                    <h2 id="an-settings" class="text-h2 font-medium">Настройки новости</h2>
                    @if ($facts)<span class="text-t2 text-muted">{{ $facts }}</span>@endif
                </div>
                <x-ui.btn square icon="x" x-on:click="settings = false" aria-label="Закрыть" />
            </div>

            <div class="flex min-h-0 flex-1 flex-col overflow-y-auto px-6 pb-6">
                @if ($stats)
                    <section class="flex flex-col gap-4 border-t border-line py-6" aria-labelledby="an-stats">
                        <div class="flex items-center justify-between gap-4">
                            <h3 id="an-stats" class="text-t1 font-medium">Прочитали</h3>
                            <span class="text-t2 text-muted"><x-ui.em>{{ $stats['read'] }}</x-ui.em> из {{ $stats['total'] }}</span>
                        </div>
                        <x-ui.progress :value="$stats['read']" :max="$stats['total']" label="Прочитали новость" />
                    </section>
                @endif

                <section class="flex flex-col gap-4 border-t border-line py-6" aria-labelledby="an-who">
                    <h3 id="an-who" class="text-t1 font-medium">Кому</h3>
                    @if ($notified)
                        <div class="flex flex-col gap-1">
                            <span class="text-t1-s font-medium">{{ $audiences[$audience] ?? '' }}</span>
                            <span class="text-t2 text-muted">Уведомление получили {{ $who }}. Адресатов уже не изменить.</span>
                        </div>
                    @else
                        <x-ui.seg :items="$audiences" model="audience" :active="$audience" aria-label="Кому показать новость" />
                        <span class="text-t2 text-muted">Уведомление получат {{ $who }}</span>
                    @endif
                </section>

                @if ($status !== 'published')
                    <section class="flex flex-col gap-4 border-t border-line py-6" aria-labelledby="an-when">
                        <h3 id="an-when" class="text-t1 font-medium">Когда опубликовать</h3>
                        <x-ui.seg :items="['now' => 'Сразу', 'later' => 'По времени']" model="when" :active="$when" aria-label="Когда опубликовать" />
                        @if ($when === 'later')
                            <x-ui.field label="Дата и время" name="publishAt" type="datetime-local" wire:model="publishAt" hint="По московскому времени" />
                        @endif
                    </section>
                @endif

                <section class="flex flex-col gap-4 border-t border-line py-6" aria-labelledby="an-show">
                    <h3 id="an-show" class="text-t1 font-medium">Как показать</h3>
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex min-w-0 flex-col gap-1">
                            <span class="text-t1-s font-medium">Важная</span>
                            <span class="text-t2 text-muted">Карточка на главной, пока не прочитают</span>
                        </div>
                        <x-ui.switch :checked="$important" label="Важная" wire:click="$toggle('important')" />
                    </div>
                    <div class="flex items-center justify-between gap-4 border-t border-line pt-4">
                        <div class="flex min-w-0 flex-col gap-1">
                            <span class="text-t1-s font-medium">Закрепить</span>
                            <span class="text-t2 text-muted">Вверху списка новостей</span>
                        </div>
                        <x-ui.switch :checked="$pinned" label="Закрепить" wire:click="$toggle('pinned')" />
                    </div>
                    <div class="flex items-center justify-between gap-4 border-t border-line pt-4">
                        <div class="flex min-w-0 flex-col gap-1">
                            <span class="text-t1-s font-medium">Письмо на почту</span>
                            <span class="text-t2 text-muted">{{ $notified ? ($sendMail ? 'Письмо отправлено' : 'Письмо не отправляли') : 'Вместе с уведомлением в кабинете — для того, что нельзя пропустить' }}</span>
                        </div>
                        @unless ($notified)
                            <x-ui.switch :checked="$sendMail" label="Письмо на почту" wire:click="$toggle('sendMail')" />
                        @endunless
                    </div>
                </section>

                @if ($model)
                    <div class="flex flex-wrap gap-x-6 gap-y-2 border-t border-line pt-6">
                        @if ($status === 'published')
                            <button type="button" class="link text-t2" wire:click="unpublish">Снять с публикации</button>
                        @elseif ($status === 'scheduled')
                            <button type="button" class="link text-t2" wire:click="unpublish">Отменить публикацию</button>
                        @endif
                        <button type="button" class="link text-t2" x-on:click="settings = false" wire:click="askDelete">Удалить новость</button>
                    </div>
                @endif
            </div>
        </aside>
    </div>

    @if ($confirmPublish)
        <x-ui.modal title="Опубликовать новость?" :sub="$heading" close="closePublish" width="s">
            <p class="text-t1-s">Уведомление получат {{ $who }}{{ $sendMail ? ', и ещё придёт письмо на почту' : '' }}. Отменить рассылку после публикации нельзя.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="closePublish">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="publish" wire:loading.attr="disabled" wire:target="publish">Опубликовать</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($confirmDelete && $model)
        <x-ui.modal title="Удалить новость?" :sub="$model->title" close="closeDelete" width="s">
            <p class="text-t1-s">Новость пропадёт у учителей и учеников вместе со статистикой прочтений. Вернуть её нельзя.</p>
            @if ($status === 'published')
                <span class="text-t2 text-muted">Нужно только убрать из кабинетов? <button type="button" class="link" wire:click="unpublish">Снимите с публикации</button></span>
            @endif
            <x-slot:footer>
                <x-ui.btn wire:click="closeDelete">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="delete">Удалить новость</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
