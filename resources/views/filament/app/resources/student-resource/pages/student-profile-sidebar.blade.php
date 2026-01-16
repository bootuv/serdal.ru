<div class="flex items-center gap-4 p-4">
    <img src="{{ $record->avatar_url }}" class="w-28 h-28 rounded-full object-cover bg-gray-100">
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $record->name }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $record->display_role }}</p>
    </div>
</div>