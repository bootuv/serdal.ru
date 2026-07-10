<div x-data class="text-sm">
    {{-- Идёт передача --}}
    <div x-show="$store.matUpload?.active && !$store.matUpload?.done && !$store.matUpload?.failed">
        <div class="mb-1.5 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
            <span class="flex items-center gap-1.5">
                <x-filament::loading-indicator class="h-3.5 w-3.5" />
                Передаём файлы на сервер…
            </span>
            <span x-text="($store.matUpload?.progress ?? 0) + '%'"></span>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
            <div
                class="h-full rounded-full bg-primary-500 transition-all duration-300"
                :style="'width:' + ($store.matUpload?.progress ?? 0) + '%'"
            ></div>
        </div>
    </div>

    {{-- Готово --}}
    <div
        x-show="$store.matUpload?.done"
        x-cloak
        class="flex items-center gap-1.5 font-medium text-success-600 dark:text-success-400"
    >
        <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4" />
        Файлы переданы — можно загружать
    </div>

    {{-- Ошибка --}}
    <div
        x-show="$store.matUpload?.failed"
        x-cloak
        class="flex items-center gap-1.5 font-medium text-danger-600 dark:text-danger-400"
    >
        <x-filament::icon icon="heroicon-m-x-circle" class="h-4 w-4" />
        Ошибка передачи — закройте окно и попробуйте снова
    </div>
</div>
