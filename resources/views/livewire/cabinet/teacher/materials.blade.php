{{-- Материалы учителя. Макет: TeacherMaterials. Файлы с компьютера можно перетащить на страницу, файлы и папки — на папку или в строку «Все материалы › …»;
     файл на соседний файл и папку к краю соседней папки — ручной порядок. «Выбрать» — несколько файлов (как в «Записях»): переместить или удалить. --}}
@php $parentCrumb = count($crumbs) > 1 ? $crumbs[count($crumbs) - 2] : null; @endphp
<div class="flex flex-col gap-6 lg:gap-8"
     x-data="{
        drag: null, up: false, progress: 0,
        sendFiles(list) {
            const files = Array.from(list || []);
            if (! files.length) return;
            ($wire.uploadOpen ? Promise.resolve() : $wire.openUpload()).then(() => {
                $wire.uploadNames = files.map(f => f.name);
                this.up = true; this.progress = 0;
                $wire.uploadMultiple('uploads', files,
                    () => { this.up = false },
                    () => { this.up = false; $wire.uploadFailed() },
                    (e) => { this.progress = e.detail.progress });
            });
        },
     }"
     x-on:dragover.prevent
     x-on:drop.prevent="if (drag) { drag = null } else { sendFiles($event.dataTransfer.files) }"
     x-on:livewire-upload-start="up = true; progress = 0"
     x-on:livewire-upload-progress="progress = $event.detail.progress"
     x-on:livewire-upload-finish="up = false"
     x-on:livewire-upload-error="up = false; $wire.uploadFailed()">

    <x-ui.page-head title="Материалы" :sub="$sub">
        <x-slot:actions>
            @if ($selecting)
                <span class="hidden text-t2 lg:inline"><x-ui.em>{{ $picked ? 'Выбрано: ' . count($picked) : 'Выберите файлы' }}</x-ui.em></span>
                <x-ui.btn wire:click="cancelSelect" class="hidden lg:inline-flex">Отмена</x-ui.btn>
                <x-ui.btn square icon="x" wire:click="cancelSelect" class="lg:hidden" aria-label="Отмена" />
                <x-ui.btn wire:click="askBulk('move')" :disabled="! $picked" class="hidden lg:inline-flex">Переместить</x-ui.btn>
                <x-ui.btn square icon="move" wire:click="askBulk('move')" :disabled="! $picked" class="lg:hidden" aria-label="Переместить выбранные" />
                <x-ui.btn variant="dark" wire:click="askBulk('delete')" :disabled="! $picked">Удалить</x-ui.btn>
            @else
                @unless ($isEmpty)
                    <x-ui.btn wire:click="startSelect" class="hidden lg:inline-flex">Выбрать</x-ui.btn>
                @endunless
                <x-ui.btn icon="plus" wire:click="newFolder" class="hidden lg:inline-flex">Новая папка</x-ui.btn>
                <x-ui.btn square icon="plus" wire:click="newFolder" class="lg:hidden" aria-label="Новая папка" />
                <x-ui.btn variant="dark" icon="upload" wire:click="openUpload">Загрузить</x-ui.btn>
            @endif
        </x-slot:actions>
    </x-ui.page-head>

    @if ($selecting && $picked)
        <p class="text-t2 lg:hidden"><x-ui.em>Выбрано: {{ count($picked) }}</x-ui.em></p>
    @endif

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        {{-- Фокус: библиотека --}}
        <x-ui.card focus aria-label="Библиотека" class="min-w-0 lg:col-span-2">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <nav class="flex min-w-0 flex-wrap items-center gap-1" aria-label="Папки">
                    @foreach ($crumbs as $c)
                        @if (! $loop->first)<x-ui.icon name="chevron-right" size="s" class="text-faint" />@endif
                        @if ($loop->last)
                            <span class="flex h-9 min-w-0 items-center px-2 text-t1-s font-semibold" aria-current="page"><span class="truncate">{{ $c['name'] }}</span></span>
                            @if ($current && ! $searching)
                                <x-ui.btn size="s" square icon="more" wire:click="editFolder" aria-label="Переименовать, переместить или удалить папку" />
                            @endif
                        @else
                            <button type="button" wire:click="openFolder({{ $c['id'] ?? 'null' }})"
                                    x-data="{ over: false }"
                                    x-on:dragover.prevent="over = !! drag"
                                    x-on:dragleave="over = false"
                                    x-on:drop="if (drag) { $event.preventDefault(); $event.stopPropagation(); over = false;
                                        drag.type === 'file' ? $wire.moveMaterial(drag.id, {{ $c['id'] ?? 'null' }}) : $wire.moveFolderTo(drag.id, {{ $c['id'] ?? 'null' }}); drag = null }"
                                    :class="over ? 'bg-white text-ink' : 'text-muted'"
                                    class="flex h-9 items-center rounded-sm px-2 text-t1-s font-medium hover:bg-white hover:text-ink">{{ $c['name'] }}</button>
                        @endif
                    @endforeach
                </nav>
                <x-ui.search wire:model.live.debounce.400ms="search" placeholder="Поиск по всем материалам" />
            </div>

            @if ($isEmpty)
                <p class="border-t border-line pt-4 text-t2 text-muted">Пока пусто — перетащите файлы сюда или нажмите «Загрузить».</p>
            @elseif ($folders->isEmpty() && $files->isEmpty())
                <div class="flex flex-col gap-4 border-t border-line pt-4 lg:flex-row lg:items-center lg:justify-between">
                    @if ($searching)
                        <span class="text-t2 text-muted">Ничего не нашлось — проверьте написание или попробуйте другое слово.</span>
                        <x-ui.btn size="s" wire:click="clearSearch" class="self-start lg:self-auto">Сбросить поиск</x-ui.btn>
                    @else
                        <span class="text-t2 text-muted">Пока пусто — перетащите файлы сюда или нажмите «Загрузить».</span>
                        @if ($parentCrumb)
                            <x-ui.btn size="s" icon="arrow-left" wire:click="openFolder({{ $parentCrumb['id'] ?? 'null' }})" class="self-start lg:self-auto">Назад в «{{ $parentCrumb['name'] }}»</x-ui.btn>
                        @endif
                    @endif
                </div>
            @else
                <x-ui.list>
                    {{-- Папка: файл на папку — переложить внутрь; папку к верхнему/нижнему краю — порядок, в середину — внутрь --}}
                    @foreach ($folders as $f)
                        <button type="button" wire:key="folder-{{ $f['id'] }}" wire:click="openFolder({{ $f['id'] }})"
                                draggable="{{ $selecting ? 'false' : 'true' }}"
                                x-data="{ side: null }"
                                x-on:dragstart="drag = { type: 'folder', id: {{ $f['id'] }} }"
                                x-on:dragend="drag = null"
                                x-on:dragover.prevent="
                                    if (! drag || (drag.type === 'folder' && drag.id === {{ $f['id'] }})) { side = null; return }
                                    if (drag.type === 'file' || ! @js($sortable)) { side = 'in'; return }
                                    const r = $el.getBoundingClientRect(), y = ($event.clientY - r.top) / r.height;
                                    side = y < 0.25 ? 'before' : (y > 0.75 ? 'after' : 'in')"
                                x-on:dragleave="side = null"
                                x-on:drop="if (drag) { $event.preventDefault(); $event.stopPropagation(); const s = side; side = null;
                                    if (drag.type === 'file') { $wire.moveMaterial(drag.id, {{ $f['id'] }}) }
                                    else if (drag.id !== {{ $f['id'] }}) { s === 'in' ? $wire.moveFolderTo(drag.id, {{ $f['id'] }}) : (s && $wire.reorderFolder(drag.id, {{ $f['id'] }}, s === 'before')) }
                                    drag = null }"
                                :class="side === 'in' && 'bg-white'"
                                class="group relative flex w-full items-center gap-4 border-t border-line py-4 text-left last:pb-0">
                            <span x-show="side === 'before'" x-cloak class="pointer-events-none absolute inset-x-0 top-0 border-t-2 border-ink"></span>
                            <span x-show="side === 'after'" x-cloak class="pointer-events-none absolute inset-x-0 bottom-0 border-b-2 border-ink"></span>
                            <x-ui.file-tile icon="folder" onMint />
                            <x-ui.text :title="$f['name']" :sub="$f['meta']" />
                            <x-ui.icon name="chevron-right" class="text-faint group-hover:text-ink" />
                        </button>
                    @endforeach
                    {{-- Файл: на папку — переложить; на соседний файл — порядок (верхняя половина — перед ним, нижняя — после) --}}
                    @foreach ($files as $f)
                        @php $on = in_array($f['id'], $picked, true); @endphp
                        <button type="button" wire:key="file-{{ $f['id'] }}"
                                @if ($selecting) wire:click="toggle({{ $f['id'] }})" aria-pressed="{{ $on ? 'true' : 'false' }}" @else wire:click="editFile({{ $f['id'] }})" @endif
                                draggable="{{ $selecting ? 'false' : 'true' }}"
                                x-data="{ side: null }"
                                x-on:dragstart="drag = { type: 'file', id: {{ $f['id'] }} }"
                                x-on:dragend="drag = null"
                                x-on:dragover="if (! @js($sortable) || ! drag || drag.type !== 'file' || drag.id === {{ $f['id'] }}) { side = null; return }
                                    $event.preventDefault();
                                    const r = $el.getBoundingClientRect();
                                    side = ($event.clientY - r.top) / r.height < 0.5 ? 'before' : 'after'"
                                x-on:dragleave="side = null"
                                x-on:drop="if (drag && side) { $event.preventDefault(); $event.stopPropagation();
                                    $wire.reorderMaterial(drag.id, {{ $f['id'] }}, side === 'before'); side = null; drag = null }"
                                class="group relative flex w-full items-center gap-4 border-t border-line py-4 text-left last:pb-0">
                            <span x-show="side === 'before'" x-cloak class="pointer-events-none absolute inset-x-0 top-0 border-t-2 border-ink"></span>
                            <span x-show="side === 'after'" x-cloak class="pointer-events-none absolute inset-x-0 bottom-0 border-b-2 border-ink"></span>
                            @if ($selecting)
                                <span class="flex size-10 shrink-0 items-center justify-center">
                                    <span @class(['flex size-6 items-center justify-center rounded-sm',
                                                  'bg-ink text-white' => $on,
                                                  'bg-white shadow-outline-ink' => ! $on])>@if ($on)<x-ui.icon name="check" size="s" />@endif</span>
                                </span>
                            @else
                                <x-ui.file-tile :name="$f['file']" onMint />
                            @endif
                            <span class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="truncate text-t1 font-medium">{{ $f['title'] }}</span>
                                <span class="flex min-w-0 items-center gap-1 text-t2 text-muted">
                                    <x-ui.icon :name="$f['lock'] ? 'lock' : 'users'" size="s" />
                                    <span class="truncate">{{ $f['access'] }}</span>
                                </span>
                            </span>
                            <span class="hidden shrink-0 whitespace-nowrap text-t2 text-muted lg:inline">{{ implode(' · ', array_filter([$f['size'], $f['date']])) }}</span>
                            @unless ($selecting)<x-ui.icon name="chevron-right" class="text-faint group-hover:text-ink" />@endunless
                        </button>
                    @endforeach
                </x-ui.list>
                @if ($hasMore)
                    <x-ui.btn size="s" class="self-start" wire:click="showMore">Показать ещё</x-ui.btn>
                @endif
            @endif
        </x-ui.card>

        {{-- Кому открыто --}}
        <x-ui.card aria-labelledby="m-access" class="min-w-0">
            <x-ui.card-head id="m-access" title="Кому открыто" />
            @if ($access->isEmpty())
                <p class="text-t2 text-muted">Пока пусто — загрузите файлы и откройте их ученикам.</p>
            @else
                <x-ui.list>
                    @foreach ($access as $a)
                        <x-ui.row wire:key="acc-{{ $loop->index }}">
                            @if ($a['kind'] === 'person')
                                <x-ui.avatar :user="$a['user']" />
                            @elseif ($a['kind'] === 'private')
                                <span class="flex size-10 shrink-0 items-center justify-center rounded bg-soft"><x-ui.icon name="lock" size="s" /></span>
                            @else
                                <x-ui.avatar group />
                            @endif
                            <x-ui.text :title="$a['name']" :sub="plural_ru($a['count'], 'файл', 'файла', 'файлов')" />
                        </x-ui.row>
                    @endforeach
                </x-ui.list>
            @endif
        </x-ui.card>
    </div>

    {{-- Окно: новая папка / папка --}}
    @if ($folderModal)
        <x-ui.modal :title="$folderModal === 'new' ? 'Новая папка' : 'Папка'" width="s" close="$set('folderModal', null)"
                    :sub="$folderModal === 'new' ? 'В «' . $crumbs[count($crumbs) - 1]['name'] . '»' : null">
            <form id="folder-form" wire:submit="saveFolder" class="flex flex-col gap-4">
                <x-ui.field label="Название" name="folderName" wire:model="folderName" maxlength="255" autofocus />
                @if ($folderModal === 'edit')
                    <x-ui.select label="Где лежит" name="folderParent" :options="$parentOptions" wire:model="folderParent" />
                @endif
            </form>
            @if ($folderModal === 'edit')
                <x-slot:note><button type="button" class="link text-t2" wire:click="$set('confirm', 'folder')">Удалить папку</button></x-slot:note>
            @endif
            <x-slot:footer>
                <x-ui.btn wire:click="$set('folderModal', null)">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" type="submit" form="folder-form">{{ $folderModal === 'new' ? 'Создать' : 'Сохранить' }}</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Окно: файл (название, доступ, папка) --}}
    @if ($file)
        <x-ui.modal :title="$file->title" width="m" close="closeFile"
                    :sub="implode(' · ', array_filter([mb_strtoupper(pathinfo($file->original_name ?: $file->file_path, PATHINFO_EXTENSION)) ?: null, $file->file_size > 0 ? str_replace('.', ',', $file->formatted_size) : null, 'добавлен ' . \App\Support\HumanDate::date($file->created_at)]))">
            <form id="file-form" wire:submit="saveFile" class="flex flex-col gap-4">
                {{-- Файл и замена (тот же лимит, что при загрузке) --}}
                <div class="flex flex-col gap-2" x-data
                     x-on:livewire-upload-start.stop x-on:livewire-upload-progress.stop x-on:livewire-upload-finish.stop
                     x-on:livewire-upload-error.stop="$wire.replacementFailed()">
                    <div class="flex items-center gap-3">
                        @php $shownName = $replacement ? ($replacementName ?: $replacement->getClientOriginalName()) : ($file->original_name ?: basename($file->file_path)); @endphp
                        <x-ui.file-tile :name="$shownName" />
                        <x-ui.text :title="$shownName"
                                   :sub="$replacement ? 'Заменит текущий файл после сохранения' : ($file->file_size > 0 ? str_replace('.', ',', $file->formatted_size) : null)" />
                        @if ($replacement)
                            <x-ui.btn size="s" wire:click="cancelReplacement">Оставить прежний</x-ui.btn>
                        @else
                            <x-ui.btn size="s" x-on:click="$refs.replace.click()" wire:loading.attr="disabled" wire:target="replacement">Заменить</x-ui.btn>
                        @endif
                    </div>
                    <input type="file" class="hidden" x-ref="replace" wire:model="replacement"
                           x-on:change="$wire.replacementName = $event.target.files[0]?.name ?? null">
                    <p wire:loading wire:target="replacement" class="text-t2 text-muted">Передаём файл…</p>
                    @error('replacement')<p class="text-t2 font-medium text-danger-fg">{{ $message }}</p>@enderror
                </div>
                <x-ui.field label="Название" name="fileTitle" wire:model="fileTitle" maxlength="255" />
                <x-ui.field label="Описание" name="fileDescription" rows="2" optional wire:model="fileDescription" maxlength="1000"
                            placeholder="Например: для подготовки к контрольной" hint="Ученики найдут файл и по описанию" />
                @include('livewire.cabinet.teacher.partials.material-access', ['visibility' => $fileVisibility, 'model' => 'fileVisibility', 'roomsModel' => 'fileRooms'])
                <x-ui.select label="Папка" name="fileFolder" :options="$folderOptions" wire:model="fileFolder" />
            </form>
            <x-slot:note>
                <span class="flex items-center gap-4">
                    <a href="{{ $file->file_url }}" target="_blank" rel="noopener" class="link text-t2">Открыть файл</a>
                    <button type="button" class="link text-t2" wire:click="$set('confirm', 'file')">Удалить</button>
                </span>
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="closeFile" class="hidden lg:inline-flex">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" type="submit" form="file-form" wire:loading.attr="disabled" wire:target="replacement,saveFile">Сохранить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Окно: загрузка файлов --}}
    @if ($uploadOpen)
        <x-ui.modal title="Загрузка файлов" width="m" close="cancelUpload"
                    :sub="'В «' . ($folderOptions[$uploadFolder] ?? 'Все материалы') . '»'">
            <x-ui.dropzone wire:model="uploads" hint="До 200 МБ каждый"
                           x-on:change="$wire.uploadNames = Array.from($event.target.files).map(f => f.name)" />
            <p x-show="up" x-cloak class="text-t2 text-muted">Передаём файлы… <span x-text="progress + '%'"></span></p>
            @error('uploads')<p class="text-t2 font-medium text-danger-fg">{{ $message }}</p>@enderror

            @if ($uploads)
                <x-ui.list>
                    @foreach ($uploads as $i => $u)
                        @php $uName = $uploadNames[$i] ?? $u->getClientOriginalName(); @endphp
                        <x-ui.row wire:key="up-{{ $i }}">
                            <x-ui.file-tile :name="$uName" />
                            <x-ui.text :title="$uName" :sub="\Illuminate\Support\Number::fileSize($u->getSize(), precision: 1)" />
                        </x-ui.row>
                    @endforeach
                </x-ui.list>
            @endif

            <x-ui.select label="Папка" name="uploadFolder" :options="$folderOptions" wire:model.live="uploadFolder" />
            @include('livewire.cabinet.teacher.partials.material-access', ['visibility' => $uploadVisibility, 'model' => 'uploadVisibility', 'roomsModel' => 'uploadRooms'])

            <x-slot:footer>
                <x-ui.btn wire:click="cancelUpload">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveUpload" x-bind:disabled="up">Загрузить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Несколько файлов: переместить --}}
    @if ($bulk === 'move' && $picked)
        <x-ui.modal :title="'Переместить ' . plural_ru(count($picked), 'файл', 'файла', 'файлов')" width="s" close="$set('bulk', null)">
            <x-ui.select label="Папка" name="bulkFolder" :options="$folderOptions" wire:model="bulkFolder" />
            <x-slot:footer>
                <x-ui.btn wire:click="$set('bulk', null)">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="moveSelected">Переместить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Подтверждения удаления --}}
    @if ($bulk === 'delete' && $picked)
        <x-ui.modal :title="'Удалить ' . plural_ru(count($picked), 'файл', 'файла', 'файлов') . '?'" width="s" close="$set('bulk', null)">
            <p class="text-t1">Файлы удалятся безвозвратно — ученики их больше не увидят.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="$set('bulk', null)">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="deleteSelected">Удалить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
    @if ($confirm === 'file' && $file)
        <x-ui.modal title="Удалить файл?" :sub="$file->title" width="s" close="$set('confirm', null)">
            <p class="text-t1">Файл удалится безвозвратно — ученики его больше не увидят.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="$set('confirm', null)">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="deleteFile">Удалить файл</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
    @if ($confirm === 'folder' && $current)
        <x-ui.modal title="Удалить папку?" :sub="$current->name" width="s" close="$set('confirm', null)">
            <p class="text-t1">Файлы и вложенные папки не удалятся — они переместятся на уровень выше.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="$set('confirm', null)">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="deleteFolder">Удалить папку</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
