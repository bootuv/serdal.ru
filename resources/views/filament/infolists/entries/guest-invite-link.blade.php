<div class="space-y-1">
    <div class="text-sm font-medium text-gray-950 dark:text-white">
        Гостевая ссылка
    </div>

    <div 
        class="flex items-center gap-2 px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
        x-data="{}"
        @click="
            navigator.clipboard.writeText(document.getElementById('guest-link-{{ $getRecord()->id }}').textContent.trim());
            $wire.mountAction('copyGuestLink');
        "
        x-tooltip="'Нажмите, чтобы скопировать'"
    >
        <x-heroicon-o-link class="w-4 h-4 text-gray-400 flex-shrink-0" />
        <span class="flex-1 text-sm text-gray-600 dark:text-gray-300 truncate select-all" id="guest-link-{{ $getRecord()->id }}">
            {{ route('rooms.join', $getRecord()) }}
        </span>
        <x-heroicon-o-clipboard-document class="h-4 w-4 text-gray-400" />
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Позволяет присоединиться к занятию без привязки к нему
    </p>
</div>