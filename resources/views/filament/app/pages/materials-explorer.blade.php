<x-filament-panels::page>
    @php
        $folders = $this->folders;
        $allMaterials = $this->materials;
        $hasMore = $allMaterials->count() > $this->limit;
        $materials = $allMaterials->take($this->limit);
        $searching = filled(trim($this->search));
        $breadcrumbs = $this->breadcrumbFolders;
    @endphp

    <div
        class="flex flex-col gap-6"
        x-data="{ dragging: 0, drag: null }"
        x-on:dragenter.prevent="
            const t = Array.from($event.dataTransfer.types);
            if (t.includes('Files') && !drag) dragging++
        "
        x-on:dragover.prevent
        x-on:dragleave.prevent="dragging = Math.max(0, dragging - 1)"
        x-on:drop.prevent="
            dragging = 0;
            if (drag) { drag = null; return; }
            const files = Array.from($event.dataTransfer.files);
            if (files.length) $wire.uploadMultiple('pendingFiles', files);
        "
    >

    {{-- Скрытый input для кнопки «Загрузить файлы» --}}
    <input
        type="file"
        id="materials-file-picker"
        multiple
        class="hidden"
        x-on:change="
            const files = Array.from($event.target.files);
            if (files.length) $wire.uploadMultiple('pendingFiles', files);
            $event.target.value = '';
        "
    />

    {{-- Оверлей при перетаскивании файлов извне --}}
    <div
        x-show="dragging > 0"
        x-transition.opacity.duration.150ms
        x-cloak
        class="pointer-events-none fixed inset-0 z-40 flex items-center justify-center bg-gray-950/40 backdrop-blur-sm"
    >
        <div class="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-white/80 bg-white/10 px-14 py-10 text-white">
            <x-filament::icon icon="heroicon-o-arrow-up-tray" class="h-12 w-12" />
            <p class="text-lg font-semibold">
                Отпустите, чтобы загрузить
            </p>
            <p class="text-sm opacity-80">
                {{ $this->activeFolder ? 'В папку «' . $this->activeFolder->name . '»' : 'В корень каталога' }}
            </p>
        </div>
    </div>

    {{-- Индикатор передачи файлов --}}
    <div
        wire:loading.flex
        wire:target="pendingFiles"
        class="fixed bottom-6 right-6 z-40 items-center gap-3 rounded-xl bg-gray-900 px-4 py-3 text-white shadow-lg dark:bg-white dark:text-gray-900"
    >
        <x-filament::loading-indicator class="h-5 w-5" />
        <span class="text-sm font-medium">Передаём файлы…</span>
    </div>

    {{-- Панель: хлебные крошки + поиск --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 flex-wrap items-center gap-1 text-sm">
            {{-- Корень: клик — переход, drop — перемещение в корень --}}
            <button
                type="button"
                wire:click="closeFolder"
                x-data="{ over: false }"
                x-on:dragover.prevent="if (drag) over = true"
                x-on:dragleave="over = false"
                x-on:drop.prevent.stop="
                    over = false;
                    if (!drag) return;
                    drag.type === 'material' ? $wire.moveMaterial(drag.id, null) : $wire.moveFolder(drag.id, null);
                    drag = null;
                "
                :class="over && 'ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-500/10'"
                @class([
                    'inline-flex items-center gap-1.5 rounded-lg px-2 py-1 font-medium transition',
                    'text-primary-600 hover:bg-gray-100 dark:text-primary-400 dark:hover:bg-white/5' => $this->folder,
                    'text-gray-950 dark:text-white cursor-default' => ! $this->folder,
                ])
            >
                <x-filament::icon icon="heroicon-m-home" class="h-4 w-4" />
                Каталог
            </button>

            @foreach ($breadcrumbs as $crumb)
                <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-400" />
                <button
                    type="button"
                    wire:click="openFolder({{ $crumb->id }})"
                    x-data="{ over: false }"
                    x-on:dragover.prevent="if (drag) over = true"
                    x-on:dragleave="over = false"
                    x-on:drop.prevent.stop="
                        over = false;
                        if (!drag) return;
                        drag.type === 'material' ? $wire.moveMaterial(drag.id, {{ $crumb->id }}) : $wire.moveFolder(drag.id, {{ $crumb->id }});
                        drag = null;
                    "
                    :class="over && 'ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-500/10'"
                    @class([
                        'inline-flex min-w-0 items-center gap-1.5 rounded-lg px-2 py-1 font-medium transition',
                        'text-primary-600 hover:bg-gray-100 dark:text-primary-400 dark:hover:bg-white/5' => ! $loop->last,
                        'text-gray-950 dark:text-white cursor-default' => $loop->last,
                    ])
                >
                    <x-filament::icon icon="heroicon-s-folder" class="h-4 w-4 shrink-0 text-amber-400" />
                    <span class="truncate">{{ $crumb->name }}</span>
                </button>
            @endforeach
        </div>

        <div class="w-full sm:w-72">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Поиск по всем материалам"
                />
            </x-filament::input.wrapper>
        </div>
    </div>

    {{-- Панель массовых действий --}}
    @if (count($this->selected) > 0)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-primary-50 px-4 py-2.5 ring-1 ring-primary-600/20 dark:bg-primary-500/10 dark:ring-primary-400/30">
            <p class="text-sm font-medium text-primary-700 dark:text-primary-300">
                Выбрано: {{ count($this->selected) }} <span class="font-normal opacity-70">(перетащите на папку, чтобы переместить)</span>
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::button color="gray" size="sm" wire:click="selectAllVisible">
                    Выбрать все
                </x-filament::button>
                <x-filament::button color="gray" size="sm" wire:click="clearSelection">
                    Снять выделение
                </x-filament::button>
                <x-filament::button
                    color="danger"
                    size="sm"
                    icon="heroicon-m-trash"
                    wire:click="mountAction('deleteSelected')"
                >
                    Удалить выбранные
                </x-filament::button>
            </div>
        </div>
    @endif

    @if ($folders->isEmpty() && $materials->isEmpty())
        {{-- Пустое состояние --}}
        <div class="flex flex-col items-center justify-center gap-3 rounded-xl bg-white py-16 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="rounded-full bg-gray-100 p-3 dark:bg-gray-800">
                <x-filament::icon icon="heroicon-o-folder-open" class="h-8 w-8 text-gray-400" />
            </div>
            <p class="text-base font-semibold text-gray-950 dark:text-white">
                {{ $searching ? 'Ничего не найдено' : 'Здесь пока пусто' }}
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $searching ? 'Попробуйте изменить запрос.' : 'Перетащите файлы сюда или нажмите «Загрузить файлы».' }}
            </p>
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            {{-- Папки --}}
            @foreach ($folders as $folder)
                <div
                    wire:key="folder-{{ $folder->id }}"
                    draggable="true"
                    x-data="{ side: null }"
                    x-on:dragstart="drag = { type: 'folder', id: {{ $folder->id }} }; $event.dataTransfer.effectAllowed = 'move'"
                    x-on:dragend="drag = null"
                    x-on:dragover.prevent="
                        if (!drag || (drag.type === 'folder' && drag.id === {{ $folder->id }})) return;
                        if (drag.type === 'material') { side = 'center'; return; }
                        const r = $el.getBoundingClientRect();
                        const x = ($event.clientX - r.left) / r.width;
                        side = x < 0.25 ? 'left' : (x > 0.75 ? 'right' : 'center');
                    "
                    x-on:dragleave="side = null"
                    x-on:drop.prevent.stop="
                        const s = side; side = null;
                        if (!drag || (drag.type === 'folder' && drag.id === {{ $folder->id }})) { drag = null; return; }
                        if (drag.type === 'material') {
                            $wire.moveMaterial(drag.id, {{ $folder->id }});
                        } else if (s === 'center') {
                            $wire.moveFolder(drag.id, {{ $folder->id }});
                        } else if (s) {
                            $wire.reorderFolders(drag.id, {{ $folder->id }}, s === 'left');
                        }
                        drag = null;
                    "
                    :class="side === 'center' && 'ring-2 ring-primary-500 scale-[1.03]'"
                    class="group relative flex cursor-pointer flex-col items-center rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-900 dark:ring-white/10"
                    wire:click="openFolder({{ $folder->id }})"
                >
                    {{-- Индикаторы позиции вставки (ровно по центру зазора между плитками) --}}
                    <div x-show="side === 'left'" x-cloak class="pointer-events-none absolute -left-[9px] bottom-1 top-1 z-20 w-1.5 rounded-full bg-primary-500 shadow"></div>
                    <div x-show="side === 'right'" x-cloak class="pointer-events-none absolute -right-[9px] bottom-1 top-1 z-20 w-1.5 rounded-full bg-primary-500 shadow"></div>

                    {{-- Оверлей «поместить внутрь» --}}
                    <div x-show="side === 'center' && drag" x-cloak class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center rounded-xl bg-primary-500/15">
                        <div class="rounded-lg bg-primary-600 px-2.5 py-1 text-xs font-semibold text-white shadow">
                            Поместить в папку
                        </div>
                    </div>
                    <div class="absolute right-1.5 top-1.5 opacity-0 transition group-hover:opacity-100" wire:click.stop>
                        <x-filament::dropdown placement="bottom-end">
                            <x-slot name="trigger">
                                <x-filament::icon-button
                                    icon="heroicon-m-ellipsis-vertical"
                                    color="gray"
                                    size="sm"
                                    label="Действия"
                                />
                            </x-slot>
                            <x-filament::dropdown.list>
                                <x-filament::dropdown.list.item
                                    icon="heroicon-m-pencil-square"
                                    wire:click="mountAction('renameFolder', { folder: {{ $folder->id }} })"
                                >
                                    Переименовать
                                </x-filament::dropdown.list.item>
                                <x-filament::dropdown.list.item
                                    icon="heroicon-m-trash"
                                    color="danger"
                                    wire:click="mountAction('deleteFolder', { folder: {{ $folder->id }} })"
                                >
                                    Удалить
                                </x-filament::dropdown.list.item>
                            </x-filament::dropdown.list>
                        </x-filament::dropdown>
                    </div>

                    <div class="pointer-events-none flex aspect-[4/3] w-full items-center justify-center">
                        <x-filament::icon icon="heroicon-s-folder" class="h-16 w-16 text-amber-400 transition group-hover:scale-105" />
                    </div>

                    <p class="pointer-events-none line-clamp-2 w-full break-words text-center text-sm font-medium leading-snug text-gray-950 dark:text-white" title="{{ $folder->name }}">
                        {{ $folder->name }}
                    </p>
                    <p class="pointer-events-none mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                        {{ trans_choice('{0} пусто|{1} :count файл|[2,4] :count файла|[5,*] :count файлов', $folder->materials_count) }}
                    </p>
                </div>
            @endforeach

            {{-- Файлы --}}
            @foreach ($materials as $material)
                @php
                    $isSelected = in_array($material->id, $this->selected);
                    $kindStyles = match ($material->file_kind) {
                        'image' => 'bg-sky-50 text-sky-500 dark:bg-sky-950 dark:text-sky-400',
                        'video' => 'bg-violet-50 text-violet-500 dark:bg-violet-950 dark:text-violet-400',
                        'audio' => 'bg-pink-50 text-pink-500 dark:bg-pink-950 dark:text-pink-400',
                        'pdf' => 'bg-red-50 text-red-500 dark:bg-red-950 dark:text-red-400',
                        'document' => 'bg-blue-50 text-blue-500 dark:bg-blue-950 dark:text-blue-400',
                        'spreadsheet' => 'bg-emerald-50 text-emerald-500 dark:bg-emerald-950 dark:text-emerald-400',
                        'presentation' => 'bg-orange-50 text-orange-500 dark:bg-orange-950 dark:text-orange-400',
                        'archive' => 'bg-amber-50 text-amber-500 dark:bg-amber-950 dark:text-amber-400',
                        default => 'bg-gray-50 text-gray-400 dark:bg-gray-800 dark:text-gray-500',
                    };
                @endphp

                <div
                    wire:key="material-{{ $material->id }}"
                    draggable="true"
                    x-data="{ side: null }"
                    x-on:dragstart="drag = { type: 'material', id: {{ $material->id }} }; $event.dataTransfer.effectAllowed = 'move'"
                    x-on:dragend="drag = null"
                    x-on:dragover.prevent="
                        if (!drag || drag.type !== 'material' || drag.id === {{ $material->id }}) return;
                        const r = $el.getBoundingClientRect();
                        const x = ($event.clientX - r.left) / r.width;
                        side = x < 0.25 ? 'left' : (x > 0.75 ? 'right' : 'center');
                    "
                    x-on:dragleave="side = null"
                    x-on:drop.prevent.stop="
                        const s = side; side = null;
                        if (drag && drag.type === 'material' && drag.id !== {{ $material->id }}) {
                            if (s === 'center') {
                                $wire.mountAction('createFolderFromFiles', { dragged: drag.id, target: {{ $material->id }} });
                            } else if (s) {
                                $wire.reorderMaterials(drag.id, {{ $material->id }}, s === 'left');
                            }
                        }
                        drag = null;
                    "
                    @class([
                        'group relative flex cursor-pointer flex-col rounded-xl bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-900',
                        'ring-2 ring-primary-500 dark:ring-primary-400' => $isSelected,
                        'ring-1 ring-gray-950/5 dark:ring-white/10' => ! $isSelected,
                    ])
                    x-on:click="$wire.selected.length > 0
                        ? $wire.toggleSelect({{ $material->id }})
                        : window.location = '{{ \App\Filament\App\Resources\MaterialResource::getUrl('edit', ['record' => $material]) }}'"
                >
                    {{-- Индикаторы позиции вставки (ровно по центру зазора между плитками) --}}
                    <div x-show="side === 'left'" x-cloak class="pointer-events-none absolute -left-[9px] bottom-1 top-1 z-20 w-1.5 rounded-full bg-primary-500 shadow"></div>
                    <div x-show="side === 'right'" x-cloak class="pointer-events-none absolute -right-[9px] bottom-1 top-1 z-20 w-1.5 rounded-full bg-primary-500 shadow"></div>

                    {{-- Оверлей «создать папку из двух файлов» --}}
                    <div x-show="side === 'center' && drag" x-cloak class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center rounded-xl bg-primary-500/15 ring-2 ring-inset ring-primary-500">
                        <div class="flex items-center gap-1.5 rounded-lg bg-primary-600 px-2.5 py-1 text-xs font-semibold text-white shadow">
                            <x-filament::icon icon="heroicon-m-folder-plus" class="h-3.5 w-3.5" />
                            Создать папку
                        </div>
                    </div>

                    {{-- Чекбокс выделения --}}
                    <div
                        @class([
                            'absolute left-1.5 top-1.5 z-10 transition',
                            'opacity-100' => $isSelected || count($this->selected) > 0,
                            'opacity-0 group-hover:opacity-100' => ! $isSelected && count($this->selected) === 0,
                        ])
                        x-on:click.stop
                    >
                        <input
                            type="checkbox"
                            wire:click="toggleSelect({{ $material->id }})"
                            @checked($isSelected)
                            class="h-5 w-5 cursor-pointer rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                        />
                    </div>

                    {{-- Меню --}}
                    <div class="absolute right-1.5 top-1.5 z-10 opacity-0 transition group-hover:opacity-100" x-on:click.stop>
                        <x-filament::dropdown placement="bottom-end">
                            <x-slot name="trigger">
                                <x-filament::icon-button
                                    icon="heroicon-m-ellipsis-vertical"
                                    color="gray"
                                    size="sm"
                                    label="Действия"
                                    class="bg-white/80 backdrop-blur dark:bg-gray-900/80"
                                />
                            </x-slot>
                            <x-filament::dropdown.list>
                                <x-filament::dropdown.list.item
                                    icon="heroicon-m-arrow-top-right-on-square"
                                    :href="$material->file_url"
                                    target="_blank"
                                    tag="a"
                                >
                                    Открыть
                                </x-filament::dropdown.list.item>
                                <x-filament::dropdown.list.item
                                    icon="heroicon-m-pencil-square"
                                    :href="\App\Filament\App\Resources\MaterialResource::getUrl('edit', ['record' => $material])"
                                    tag="a"
                                >
                                    Изменить
                                </x-filament::dropdown.list.item>
                                <x-filament::dropdown.list.item
                                    icon="heroicon-m-trash"
                                    color="danger"
                                    wire:click="mountAction('deleteMaterial', { material: {{ $material->id }} })"
                                >
                                    Удалить
                                </x-filament::dropdown.list.item>
                            </x-filament::dropdown.list>
                        </x-filament::dropdown>
                    </div>

                    {{-- Превью --}}
                    <div class="pointer-events-none relative aspect-[4/3] w-full overflow-hidden rounded-t-xl">
                        @if ($material->thumbnail_url)
                            <img
                                src="{{ $material->thumbnail_url }}"
                                alt="{{ $material->title }}"
                                loading="lazy"
                                class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.04]"
                            />
                        @else
                            <div class="flex h-full w-full items-center justify-center {{ $kindStyles }}">
                                <x-filament::icon :icon="$material->file_kind_icon" class="h-12 w-12" />
                            </div>
                        @endif

                        {{-- Мини-значок видимости --}}
                        <div
                            class="absolute bottom-1.5 left-1.5 rounded-md p-1 {{ match ($material->visibility) {
                                \App\Models\TeacherMaterial::VISIBILITY_PRIVATE => 'bg-gray-600/90 text-white',
                                \App\Models\TeacherMaterial::VISIBILITY_ROOMS => 'bg-amber-500/90 text-white',
                                default => 'bg-emerald-500/90 text-white',
                            } }}"
                            title="{{ $material->visibility_label }}"
                        >
                            <x-filament::icon :icon="$material->visibility_icon" class="h-3.5 w-3.5" />
                        </div>

                        {{-- Расширение --}}
                        <div class="absolute bottom-1.5 right-1.5 rounded bg-black/55 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white backdrop-blur-sm">
                            {{ strtoupper(pathinfo($material->file_path, PATHINFO_EXTENSION)) }}
                        </div>
                    </div>

                    {{-- Подпись --}}
                    <div class="pointer-events-none flex flex-1 flex-col p-2.5">
                        <p class="line-clamp-2 break-words text-center text-sm font-medium leading-snug text-gray-950 dark:text-white" title="{{ $material->title }}">
                            {{ $material->title }}
                        </p>
                        <p class="mt-0.5 text-center text-xs text-gray-400 dark:text-gray-500">
                            @if ($searching && $material->folder)
                                <x-filament::icon icon="heroicon-m-folder" class="inline h-3 w-3" />
                                {{ $material->folder->name }} ·
                            @endif
                            {{ $material->formatted_size }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($hasMore)
            <div class="flex justify-center pt-2">
                <x-filament::button color="gray" wire:click="showMore">
                    Показать ещё
                </x-filament::button>
            </div>
        @endif
    @endif

    </div> {{-- /drop-зона --}}
</x-filament-panels::page>
