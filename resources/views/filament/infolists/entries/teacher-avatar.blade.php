<div class="flex items-center gap-2">
    <img class="inline-block h-8 w-8 rounded-full object-cover" src="{{ $getRecord()->user->avatar_url }}"
        alt="{{ $getRecord()->user->name }}">
    <span class="text-sm text-gray-900 dark:text-gray-100">{{ $getRecord()->user->name }}</span>
</div>