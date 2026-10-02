<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head :title="$heading" :sub="$facts" :back="route('cabinet.admin.blog')" back-label="Блог">
        <x-slot:actions>
            <div class="hidden items-center gap-4 lg:flex">
                @if ($siteUrl)<a href="{{ $siteUrl }}" target="_blank" rel="noopener" class="link text-t1-s">Как на сайте</a>@endif
                <div class="flex items-center gap-2">
                    @if (in_array($status, ['new', 'draft'], true))
                        <x-ui.btn wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft,submit,cover">Сохранить черновик</x-ui.btn>
                    @endif
                    <x-ui.btn variant="primary" wire:click="submit" wire:loading.attr="disabled" wire:target="saveDraft,submit,cover">{{ $submitLabel }}</x-ui.btn>
                </div>
            </div>
        </x-slot:actions>
    </x-ui.page-head>

    {{-- Телефон: действия шапки под заголовком --}}
    <div class="flex flex-wrap items-center gap-2 lg:hidden">
        <x-ui.btn variant="primary" wire:click="submit" wire:loading.attr="disabled" wire:target="saveDraft,submit,cover">{{ $submitLabel }}</x-ui.btn>
        @if (in_array($status, ['new', 'draft'], true))
            <x-ui.btn wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft,submit,cover">Сохранить черновик</x-ui.btn>
        @endif
    </div>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-12">
        <div class="flex w-full min-w-0 flex-col gap-6 lg:w-form lg:shrink-0">
            <x-ui.card class="gap-4" aria-label="Текст статьи">
                <label class="flex flex-col gap-2">
                    <span class="sr-only">Заголовок</span>
                    <textarea name="title" rows="1" wire:model.live.debounce.500ms="title" placeholder="Заголовок статьи"
                              x-data x-init="$nextTick(() => { $el.style.height = $el.scrollHeight + 'px' })" x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                              class="w-full resize-none overflow-hidden bg-transparent text-h1-m font-medium text-ink outline-none placeholder:text-faint lg:text-h1"></textarea>
                    @error('title')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                </label>
                <x-ui.block-editor label="Текст статьи" name="body" wire:model="body" upload-model="image" upload-method="storeImage" />
            </x-ui.card>
            <span class="text-t3 text-muted">Нажмите «/» в пустой строке, чтобы добавить заголовок, список, цитату или картинку. Выделите текст, чтобы сделать его жирным или ссылкой. Картинку можно вставить из буфера или перетащить.</span>

            @if ($model)
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    @if ($status === 'published')
                        <button type="button" class="link text-t2" wire:click="unpublish">Снять с сайта</button>
                    @elseif ($status === 'scheduled')
                        <button type="button" class="link text-t2" wire:click="unpublish">Отменить публикацию</button>
                    @endif
                    <button type="button" class="link text-t2" wire:click="askDelete">Удалить статью</button>
                </div>
            @endif
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-6">
            @if ($status !== 'published')
                <x-ui.card aria-labelledby="ba-when">
                    <x-ui.card-head id="ba-when" title="Когда опубликовать" />
                    <x-ui.seg :items="['now' => 'Сразу', 'later' => 'По времени']" model="when" :active="$when" aria-label="Когда опубликовать" />
                    @if ($when === 'later')
                        <x-ui.field label="Дата и время" name="publishAt" type="datetime-local" wire:model="publishAt" hint="По московскому времени" />
                    @endif
                </x-ui.card>
            @endif

            <x-ui.card aria-labelledby="ba-cover">
                <x-ui.card-head id="ba-cover" title="Обложка">
                    @if ($coverUrl)
                        <x-slot:action><button type="button" class="link text-t2" wire:click="removeCover">Убрать</button></x-slot:action>
                    @endif
                </x-ui.card-head>
                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="Обложка статьи" class="w-full rounded-lg">
                @else
                    <x-ui.dropzone :multiple="false" accept="image/png,image/jpeg,image/webp" title="Перетащите картинку или выберите" hint="Видна в списке статей и при отправке ссылки в мессенджер. Лучше горизонтальная, от 1200 точек в ширину" wire:model="cover" aria-label="Обложка статьи" />
                @endif
                <span wire:loading wire:target="cover" class="text-t3 text-muted">Загружаем обложку…</span>
            </x-ui.card>

            <x-ui.card aria-labelledby="ba-seo">
                <x-ui.card-head id="ba-seo" title="Для поиска" />
                <x-ui.field label="Описание" name="excerpt" :rows="3" wire:model.live.debounce.500ms="excerpt"
                            :hint="'Показывается в поиске Яндекса и Google и в списке статей. ' . $descriptionLength . ' из 160 знаков'" />
                <x-ui.field label="Адрес статьи" name="slug" wire:model.live.debounce.500ms="slug" :placeholder="$slugPreview"
                            :hint="'serdal.ru/blog/' . $slugPreview . ($status === 'published' ? ' — после смены старые ссылки перестанут работать' : '')" />
            </x-ui.card>
        </div>
    </div>

    @if ($confirmDelete && $model)
        <x-ui.modal title="Удалить статью?" :sub="$model->title" close="closeDelete" width="s">
            <p class="text-t1-s">Статья пропадет с сайта, а ссылки на нее перестанут работать. Вернуть ее нельзя.</p>
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
