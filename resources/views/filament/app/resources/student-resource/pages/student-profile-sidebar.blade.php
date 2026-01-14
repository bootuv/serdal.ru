<div class="flex flex-col items-center text-center p-4">
    <img src="{{ $record->avatar_url }}"
        class="w-24 h-24 rounded-full object-cover shadow-md mb-3 ring-2 ring-gray-100 dark:ring-gray-800">
    <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $record->name }}</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $record->display_role }}</p>
</div>