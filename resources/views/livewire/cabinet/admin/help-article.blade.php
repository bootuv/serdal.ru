<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head :title="$heading" :sub="$facts ?: null" :back="route('cabinet.admin.help')" back-label="База знаний">
        <x-slot:actions>
            <div class="hidden items-center gap-4 lg:flex">
                @if ($siteUrl)<a href="{{ $siteUrl }}" target="_blank" rel="noopener" class="link text-t1-s">Как на сайте</a>@endif
                @if ($saved)<x-ui.badge tone="ok">Сохранено</x-ui.badge>@endif
                <x-ui.btn variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save,video">Сохранить</x-ui.btn>
            </div>
        </x-slot:actions>
    </x-ui.page-head>

    {{-- Телефон: действия шапки под заголовком --}}
    <div class="flex flex-wrap items-center gap-4 lg:hidden">
        <x-ui.btn variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save,video">Сохранить</x-ui.btn>
        @if ($saved)<x-ui.badge tone="ok">Сохранено</x-ui.badge>@endif
        @if ($siteUrl)<a href="{{ $siteUrl }}" target="_blank" rel="noopener" class="link text-t1-s">Как на сайте</a>@endif
    </div>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-12">
        <div class="flex w-full min-w-0 flex-col gap-6 lg:w-form lg:shrink-0">
            <x-ui.field label="Заголовок" name="title" wire:model.live.debounce.500ms="title" />

            {{-- Категория: группы «Для учеников» / «Для учителей» (x-ui.select не умеет группы) --}}
            <label class="flex flex-col gap-2">
                <span class="text-t2 font-medium">Категория</span>
                <span class="relative flex">
                    <select name="categoryId" wire:model.live="categoryId" @class(['field appearance-none pr-12', 'shadow-outline-ink' => $errors->has('categoryId')])>
                        @if ($categories->isEmpty())<option value="">Сначала создайте категорию</option>@endif
                        @foreach ($categories as $group => $options)
                            <optgroup label="{{ $group }}">
                                @foreach ($options as $value => $text)<option value="{{ $value }}">{{ $text }}</option>@endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-4 top-3 text-muted" />
                </span>
                @error('categoryId')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            </label>

            <x-ui.field label="Короткое описание" name="excerpt" :rows="2" wire:model="excerpt" hint="Видно в списке статей и в поиске" />

            <x-ui.editor-media label="Текст" name="content" wire:model="content" upload-model="image" upload-method="storeImage" />

            @if ($article)
                <button type="button" class="link self-start text-t2" wire:click="askDelete">Удалить статью</button>
            @endif
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-6">
            <x-ui.card aria-labelledby="ar-pub">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex min-w-0 flex-col gap-1">
                        <h2 id="ar-pub" class="text-h2 font-medium">Опубликована</h2>
                        <span class="text-t2 text-muted">{{ $published ? 'Видна на сайте' : 'Видна только в админке' }}</span>
                    </div>
                    <x-ui.switch :checked="$published" label="Опубликована" wire:click="togglePublished" />
                </div>
            </x-ui.card>

            <x-ui.card aria-labelledby="ar-video">
                <x-ui.card-head id="ar-video" title="Видео">
                    <x-slot:action><span class="text-t2 text-muted">в начале статьи</span></x-slot:action>
                </x-ui.card-head>
                <x-ui.seg :items="['file' => 'Файл', 'link' => 'Ссылка']" model="videoSource" :active="$videoSource" aria-label="Откуда видео" />

                @if ($videoSource === 'file')
                    @if ($video && ! $errors->has('video'))
                        <div class="flex items-center gap-3 rounded-lg p-2 shadow-line">
                            <x-ui.file-tile :name="$video->getClientOriginalName()" />
                            <x-ui.text :title="$video->getClientOriginalName()" :sub="max(1, (int) round($video->getSize() / 1048576)) . ' МБ · сохраните статью'" />
                            <x-ui.btn size="s" square icon="x" wire:click="discardVideo" aria-label="Убрать видео" />
                        </div>
                    @elseif ($videoName && ! $removeVideo)
                        <div class="flex items-center gap-3 rounded-lg p-2 shadow-line">
                            <x-ui.file-tile :name="$videoName" />
                            <x-ui.text :title="$videoName" sub="Загружено на сайт" />
                            <x-ui.btn size="s" square icon="x" wire:click="discardVideo" aria-label="Убрать видео" />
                        </div>
                    @else
                        <x-ui.dropzone :multiple="false" accept="video/mp4,video/webm" title="Перетащите видео сюда или выберите на компьютере" hint="MP4 или WebM · до 100 МБ" wire:model="video" aria-label="Видео для статьи" />
                        <span wire:loading wire:target="video" class="text-t3 text-muted">Загружаем видео…</span>
                    @endif
                    @error('video')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                @else
                    <x-ui.field label="Ссылка на видео" name="videoUrl" type="url" wire:model="videoUrl" placeholder="Вставьте ссылку" hint="Kinescope, YouTube, VK Видео или Rutube" />
                @endif
            </x-ui.card>
        </div>
    </div>

    @if ($confirmDelete && $article)
        <x-ui.modal title="Удалить статью?" :sub="$article->title" close="closeDelete" width="s">
            <p class="text-t1-s">Статья пропадёт с сайта вместе с видео. Вернуть её нельзя.</p>
            @if ($article->is_published)
                <span class="text-t2 text-muted">Нужно только убрать с сайта? <button type="button" class="link" wire:click="unpublishInstead">Снимите с публикации</button></span>
            @endif
            <x-slot:footer>
                <x-ui.btn wire:click="closeDelete">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="delete">Удалить статью</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
