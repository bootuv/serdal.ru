<div class="flex items-center justify-between w-full gap-4">
    <span>Количество сессий</span>
    <div @class([
        'flex items-center gap-2 text-sm font-normal',
        'text-gray-500 dark:text-gray-400' => $count === 0,
        'text-amber-600 dark:text-amber-500' => $count > 0,
    ])>
        @svg('heroicon-m-video-camera', 'w-5 h-5 ' . ($count > 0 ? 'text-amber-600 dark:text-amber-500' : 'text-gray-400'))
        <span>Сейчас идут: {{ $count }}</span>
    </div>
</div>