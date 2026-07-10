<x-filament-panels::page>
    @php
        $teachers = $this->teachers;
        $folders = $this->folders;
        $allMaterials = $this->materials;
        $hasMore = $allMaterials->count() > $this->limit;
        $materials = $allMaterials->take($this->limit);
        $searching = filled(trim($this->search));
        $hasTeacherLevel = $this->hasTeacherLevel;
    @endphp

    {{-- Панель: хлебные крошки + поиск --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-1 text-sm">
            <button
                type="button"
                wire:click="goHome"
                @class([
                    'inline-flex items-center gap-1.5 rounded-lg px-2 py-1 font-medium transition',
                    'text-primary-600 hover:bg-gray-100 dark:text-primary-400 dark:hover:bg-white/5' => $this->folder || ($hasTeacherLevel && $this->teacher),
                    'text-gray-950 dark:text-white cursor-default' => ! $this->folder && ! ($hasTeacherLevel && $this->teacher),
                ])
            >
                <x-filament::icon icon="heroicon-m-home" class="h-4 w-4" />
                Материалы
            </button>

            @if ($hasTeacherLevel && $this->activeTeacher)
                <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-400" />
                <button
                    type="button"
                    wire:click="{{ $this->folder ? 'goBack' : '' }}"
                    @class([
                        'inline-flex min-w-0 items-center gap-1.5 rounded-lg px-2 py-1 font-medium transition',
                        'text-primary-600 hover:bg-gray-100 dark:text-primary-400 dark:hover:bg-white/5' => $this->folder,
                        'text-gray-950 dark:text-white cursor-default' => ! $this->folder,
                    ])
                >
                    <img src="{{ $this->activeTeacher->avatar_url }}" class="h-4 w-4 shrink-0 rounded-full object-cover" alt="" />
                    <span class="truncate">{{ $this->activeTeacher->name }}</span>
                </button>
            @endif

            @foreach ($this->breadcrumbFolders as $crumb)
                <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-400" />
                <button
                    type="button"
                    wire:click="openFolder({{ $crumb->id }})"
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
                    placeholder="Поиск по материалам"
                />
            </x-filament::input.wrapper>
        </div>
    </div>

    @if ($teachers->isEmpty() && $folders->isEmpty() && $materials->isEmpty())
        {{-- Пустое состояние --}}
        <div class="flex flex-col items-center justify-center gap-3 rounded-xl bg-white py-16 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="rounded-full bg-gray-100 p-3 dark:bg-gray-800">
                <x-filament::icon icon="heroicon-o-folder-open" class="h-8 w-8 text-gray-400" />
            </div>
            <p class="text-base font-semibold text-gray-950 dark:text-white">
                {{ $searching ? 'Ничего не найдено' : 'Пока нет материалов' }}
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $searching ? 'Попробуйте изменить запрос.' : 'Здесь появятся файлы, которыми поделятся ваши учителя.' }}
            </p>
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            {{-- Учителя (корень при нескольких учителях) --}}
            @foreach ($teachers as $t)
                <div
                    wire:key="teacher-{{ $t->id }}"
                    class="group flex cursor-pointer flex-col items-center rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-900 dark:ring-white/10"
                    wire:click="openTeacher({{ $t->id }})"
                >
                    <div class="flex aspect-[4/3] w-full items-center justify-center">
                        <img
                            src="{{ $t->avatar_url }}"
                            alt="{{ $t->name }}"
                            class="h-16 w-16 rounded-full object-cover ring-2 ring-gray-100 transition group-hover:scale-105 dark:ring-gray-800"
                        />
                    </div>

                    <p class="line-clamp-2 w-full break-words text-center text-sm font-medium leading-snug text-gray-950 dark:text-white" title="{{ $t->name }}">
                        {{ $t->name }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                        {{ trans_choice('{1} :count материал|[2,4] :count материала|[5,*] :count материалов', $t->materials_count) }}
                    </p>
                </div>
            @endforeach

            {{-- Папки --}}
            @foreach ($folders as $folder)
                <div
                    wire:key="folder-{{ $folder->id }}"
                    class="group flex cursor-pointer flex-col items-center rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-900 dark:ring-white/10"
                    wire:click="openFolder({{ $folder->id }})"
                >
                    <div class="flex aspect-[4/3] w-full items-center justify-center">
                        <x-filament::icon icon="heroicon-s-folder" class="h-16 w-16 text-amber-400 transition group-hover:scale-105" />
                    </div>

                    <p class="line-clamp-2 w-full break-words text-center text-sm font-medium leading-snug text-gray-950 dark:text-white" title="{{ $folder->name }}">
                        {{ $folder->name }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                        {{ trans_choice('{0} вложенные папки|{1} :count файл|[2,4] :count файла|[5,*] :count файлов', $folder->materials_count) }}
                    </p>
                </div>
            @endforeach

            {{-- Файлы --}}
            @foreach ($materials as $material)
                @php
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

                <a
                    wire:key="material-{{ $material->id }}"
                    href="{{ $material->file_url }}"
                    target="_blank"
                    rel="noopener"
                    class="group relative flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 transition hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-900 dark:ring-white/10"
                >
                    {{-- Превью --}}
                    <div class="relative aspect-[4/3] w-full overflow-hidden">
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

                        {{-- Расширение --}}
                        <div class="absolute bottom-1.5 right-1.5 rounded bg-black/55 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white backdrop-blur-sm">
                            {{ strtoupper(pathinfo($material->file_path, PATHINFO_EXTENSION)) }}
                        </div>
                    </div>

                    {{-- Подпись --}}
                    <div class="flex flex-1 flex-col p-2.5">
                        <p class="line-clamp-2 break-words text-center text-sm font-medium leading-snug text-gray-950 dark:text-white" title="{{ $material->title }}">
                            {{ $material->title }}
                        </p>
                        <p class="mt-0.5 text-center text-xs text-gray-400 dark:text-gray-500">
                            @if ($searching)
                                @if ($hasTeacherLevel && ! $this->teacher)
                                    {{ $material->teacher->name }} ·
                                @endif
                                @if ($material->folder)
                                    <x-filament::icon icon="heroicon-m-folder" class="inline h-3 w-3" />
                                    {{ $material->folder->name }} ·
                                @endif
                            @endif
                            {{ $material->formatted_size }}
                        </p>
                    </div>
                </a>
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
</x-filament-panels::page>
