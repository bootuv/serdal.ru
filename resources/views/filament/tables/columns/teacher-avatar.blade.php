<div class="flex items-center gap-2">
    <img class="inline-block h-6 w-6 rounded-full object-cover" src="{{ $getRecord()->user->avatar_url }}"
        alt="{{ $getRecord()->user->name }}">
    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $getRecord()->user->name }}</span>
</div>