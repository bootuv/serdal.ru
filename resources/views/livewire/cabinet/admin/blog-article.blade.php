{{-- Статья блога (админ и учитель): чистый лист посередине (заголовок + блочный редактор, без рамки), настройки — в панели справа.
     Панель открывается кнопкой «Настройки» и сама — при ошибке в ее полях (событие blog-settings). --}}
<div class="flex flex-col gap-8 lg:gap-12" x-data="{ settings: false }" x-on:blog-settings.window="settings = true">
    <div class="flex flex-wrap items-center justify-between gap-4" {!! $editable && $status !== 'published' ? 'wire:poll.5s.visible="autosave"' : '' !!}>
        <a href="{{ $backUrl }}" class="inline-flex items-center gap-2 text-t1-s font-medium text-muted hover:text-ink"><x-ui.icon name="arrow-left" size="s" />{{ $teacher ? 'Мои статьи' : 'Блог' }}</a>
        <div class="flex flex-wrap items-center gap-4">
            {{-- Автосохранение: что с текстом сейчас --}}
            <span class="text-t2 text-muted" wire:loading.remove wire:target="autosave">
                @if ($status === 'published' && $dirty && $editable)
                    <x-ui.em>Есть несохраненные изменения</x-ui.em>
                @elseif ($savedAt)
                    Сохранено в {{ $savedAt }}
                @elseif ($facts)
                    <span class="hidden xl:inline">{{ $facts }}</span>
                @endif
            </span>
            <span class="text-t2 text-muted" wire:loading wire:target="autosave">Сохраняем…</span>
            @if ($siteUrl)<a href="{{ $siteUrl }}" target="_blank" rel="noopener" class="link hidden text-t1-s sm:inline">Как на сайте</a>@endif

            <div class="flex items-center gap-2">
                @if ($editable)
                    <x-ui.btn icon="settings" x-on:click="settings = true">Настройки</x-ui.btn>
                @endif

                @if ($teacher)
                    @if ($editable)
                        <x-ui.btn variant="primary" wire:click="submit" wire:loading.attr="disabled" wire:target="submit,cover">{{ $submitLabel }}</x-ui.btn>
                    @elseif ($status === 'published')
                        <x-ui.btn wire:click="unpublish">Снять с сайта и изменить</x-ui.btn>
                    @endif
                @else
                    {{-- Главная кнопка со стрелкой: остальные действия публикации — в списке под ней --}}
                    <div class="relative flex" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                        <x-ui.btn variant="primary" class="rounded-r-none" wire:click="submit" wire:loading.attr="disabled" wire:target="saveDraft,submit,publishNow,cover">{{ $submitLabel }}</x-ui.btn>
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
                            @if (in_array($status, ['pending', 'returned'], true))
                                <x-ui.menu-item wire:click="askReturn">Вернуть на доработку</x-ui.menu-item>
                            @else
                                <x-ui.menu-item wire:click="saveDraft">{{ match ($status) { 'published' => 'Снять с публикации', 'scheduled' => 'Вернуть в черновики', default => 'Сохранить как черновик' } }}</x-ui.menu-item>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <article class="mx-auto flex w-full max-w-form flex-col gap-4 pb-12" aria-label="Текст статьи">
        {{-- Что со статьей сейчас: проверка, возврат, публикация (учителю) --}}
        @if ($status === 'returned' && $model->review_note)
            <div class="flex flex-col gap-1 rounded-lg bg-soft p-4">
                <span class="text-t2 font-semibold">{{ $teacher ? 'Статью вернули на доработку' : 'Вы вернули статью на доработку' }}</span>
                <p class="text-t2">{{ $model->review_note }}</p>
                @if ($teacher)<span class="text-t3 text-muted">Поправьте и нажмите «Отправить на проверку».</span>@endif
            </div>
        @elseif ($status === 'pending')
            <div class="flex flex-col gap-1 rounded-lg bg-soft p-4">
                @if ($teacher)
                    <span class="text-t2 font-semibold">Статья на проверке</span>
                    <p class="text-t2">Пришлем уведомление, когда ее опубликуют. Пока можно продолжать править — изменения сохраняются сами.</p>
                @else
                    <span class="text-t2 font-semibold">Статья учителя ждет проверки</span>
                    <p class="text-t2">Автор — {{ $model->authorName() }}. Опубликуйте ее или верните с комментарием, что поправить.</p>
                @endif
            </div>
        @elseif ($teacher && ! $editable)
            <div class="flex flex-col gap-1 rounded-lg bg-soft p-4">
                <span class="text-t2 font-semibold">{{ $status === 'published' ? 'Статья опубликована' : 'Статья принята и выйдет ' . \App\Support\HumanDate::at($model->published_at) }}</span>
                <p class="text-t2">{{ $status === 'published' ? 'Чтобы изменить ее, снимите с сайта, поправьте и отправьте на проверку снова.' : 'Изменить ее до выхода можно через поддержку.' }}</p>
            </div>
        @endif

        @if ($editable)
            {{-- wire:ignore: высоту поля под длинный заголовок задает скрипт, перерисовка Livewire (автосохранение) сбрасывала ее --}}
            <div class="flex flex-col gap-2">
                <label wire:ignore class="flex flex-col">
                    <span class="sr-only">Заголовок</span>
                    <textarea name="title" rows="1" wire:model.live.debounce.500ms="title" placeholder="Заголовок"
                          x-data x-init="$nextTick(() => { $el.style.height = $el.scrollHeight + 'px' })" x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'" x-on:resize.window="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                          class="w-full resize-none overflow-hidden bg-transparent text-h1-m font-medium text-ink outline-none placeholder:text-faint lg:text-h1"></textarea>
                </label>
                @error('title')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            </div>
            <x-ui.block-editor label="Текст статьи" name="body" wire:model="body" upload-model="image" upload-method="storeImage" video-model="video" video-method="storeVideo" />
        @else
            <h1 class="text-h1-m font-medium lg:text-h1">{{ $title }}</h1>
            @if ($body)<div class="block-content">{!! \App\Support\RichText::html($body) !!}</div>@endif
        @endif
    </article>

    {{-- Настройки статьи --}}
    <div x-show="settings" x-cloak class="fixed inset-0 z-30 flex justify-end bg-scrim"
         x-on:keydown.escape.window="settings = false" x-on:click.self="settings = false"
         x-transition:enter="transition-opacity" x-transition:enter-start="opacity-0" x-transition:leave="transition-opacity" x-transition:leave-end="opacity-0">
        <aside role="dialog" aria-modal="true" aria-labelledby="ba-settings" class="flex h-full w-full flex-col bg-white shadow-modal lg:w-drawer lg:rounded-l-xl">
            <div class="flex items-center justify-between gap-4 px-6 pb-4 pt-6">
                <div class="flex min-w-0 flex-col gap-1">
                    <h2 id="ba-settings" class="text-h2 font-medium">Настройки статьи</h2>
                    @if ($facts)<span class="text-t2 text-muted">{{ $facts }}</span>@endif
                </div>
                <x-ui.btn square icon="x" x-on:click="settings = false" aria-label="Закрыть" />
            </div>

            <div class="flex min-h-0 flex-1 flex-col overflow-y-auto px-6 pb-6">
                @if (! $teacher && $status !== 'published')
                    <section class="flex flex-col gap-4 border-t border-line py-6" aria-labelledby="ba-when">
                        <h3 id="ba-when" class="text-t1 font-medium">Когда опубликовать</h3>
                        <x-ui.seg :items="['now' => 'Сразу', 'later' => 'По времени']" model="when" :active="$when" aria-label="Когда опубликовать" />
                        @if ($when === 'later')
                            <x-ui.field label="Дата и время" name="publishAt" type="datetime-local" wire:model="publishAt" hint="По московскому времени" />
                        @endif
                    </section>
                @endif

                @unless ($teacher)
                    <section class="flex flex-col gap-4 border-t border-line py-6" aria-labelledby="ba-author">
                        <h3 id="ba-author" class="text-t1 font-medium">Автор</h3>
                        <x-ui.search-select avatars name="authorId" model="authorId" :selected="$authorId" clear="Команда Serdal" search="Имя или почта учителя"
                                            :options="collect($authors)->map(fn ($p) => ['value' => (string) $p['id'], 'title' => $p['name'], 'sub' => $p['email'], 'photo' => $p['photo']])->all()" />
                        <span class="text-t3 text-muted">Статья появится среди статей учителя на сайте, а учитель увидит ее у себя в кабинете</span>
                    </section>
                @endunless

                <section class="flex flex-col gap-4 border-t border-line py-6" aria-labelledby="ba-tags">
                    <h3 id="ba-tags" class="text-t1 font-medium">Теги</h3>
                    <x-ui.tag-picker label="Теги статьи" id="ba-tags-list" model="tags" :items="$tags" :options="$tagOptions" add="addTag" remove="removeTag" what="тег" placeholder="Найти или добавить тег"
                                     hint="Выберите из списка или напишите новый. До 8 тегов — по ним статью найдут в блоге" />
                </section>

                <section class="flex flex-col gap-4 border-t border-line py-6" aria-labelledby="ba-cover">
                    <div class="flex items-center justify-between gap-4">
                        <h3 id="ba-cover" class="text-t1 font-medium">Обложка</h3>
                        @if ($coverUrl)<button type="button" class="link text-t2" wire:click="removeCover">Убрать</button>@endif
                    </div>
                    @if ($coverUrl)
                        <img src="{{ $coverUrl }}" alt="Обложка статьи" class="w-full rounded-lg">
                    @else
                        <x-ui.dropzone :multiple="false" accept="image/png,image/jpeg,image/webp" title="Перетащите картинку или выберите" hint="Видна в списке статей и при отправке ссылки в мессенджер. Лучше горизонтальная, от 1200 точек в ширину" wire:model="cover" aria-label="Обложка статьи" />
                    @endif
                    <span wire:loading wire:target="cover" class="text-t3 text-muted">Загружаем обложку…</span>
                </section>

                <section class="flex flex-col gap-4 border-t border-line py-6" aria-labelledby="ba-seo">
                    <h3 id="ba-seo" class="text-t1 font-medium">Для поиска</h3>
                    <x-ui.field label="Описание" name="excerpt" :rows="3" wire:model.live.debounce.500ms="excerpt"
                                :hint="'Показывается в поиске Яндекса и Google и в списке статей. ' . $descriptionLength . ' из 160 знаков'" />
                    @unless ($teacher)
                        <x-ui.field label="Адрес статьи" name="slug" wire:model.live.debounce.500ms="slug" :placeholder="$slugPreview"
                                    :hint="'serdal.ru/blog/' . $slugPreview . ($status === 'published' ? ' — после смены старые ссылки перестанут работать' : '')" />
                    @endunless
                </section>

                @if ($model)
                    <div class="flex flex-wrap gap-x-6 gap-y-2 border-t border-line pt-6">
                        @if ($status === 'published')
                            <button type="button" class="link text-t2" wire:click="unpublish">Снять с сайта</button>
                        @elseif ($status === 'scheduled')
                            <button type="button" class="link text-t2" wire:click="unpublish">Отменить публикацию</button>
                        @elseif ($teacher && $status === 'pending')
                            <button type="button" class="link text-t2" x-on:click="settings = false" wire:click="withdraw">Забрать с проверки</button>
                        @endif
                        <button type="button" class="link text-t2" x-on:click="settings = false" wire:click="askDelete">Удалить статью</button>
                    </div>
                @endif
            </div>
        </aside>
    </div>

    @if ($returning)
        <x-ui.modal title="Вернуть на доработку" :sub="$title" close="closeReturn" width="s">
            <x-ui.field label="Что поправить" name="returnNote" :rows="4" wire:model="returnNote" hint="Автор увидит комментарий в статье и получит уведомление на почту" />
            <x-slot:footer>
                <x-ui.btn wire:click="closeReturn">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="returnForRework">Вернуть автору</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($confirmDelete && $model)
        <x-ui.modal title="Удалить статью?" :sub="$model->title" close="closeDelete" width="s">
            <p class="text-t1-s">{{ $status === 'published' ? 'Статья пропадет с сайта, а ссылки на нее перестанут работать.' : 'Черновик пропадет.' }} Вернуть ее нельзя.</p>
            @if ($status === 'published')
                <span class="text-t2 text-muted">Нужно только убрать с сайта? <button type="button" class="link" wire:click="unpublish">Снимите с публикации</button></span>
            @endif
            <x-slot:footer>
                <x-ui.btn wire:click="closeDelete">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="delete">Удалить статью</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
