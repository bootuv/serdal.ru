<div class="fi-in-repeatable-item-ctn divide-y divide-gray-200 dark:divide-gray-700">
    @forelse($history as $session)
        <a href="{{ \App\Filament\App\Resources\MeetingSessionResource::getUrl('view', ['record' => $session['session_id']]) }}"
            class="flex items-center justify-between gap-4 p-3 hover:bg-gray-50 dark:hover:bg-white/5 transition duration-75 cursor-pointer">
            <div class="flex items-center gap-4 min-w-0">
                <span class="fi-in-text-item-label text-sm font-medium text-gray-950 dark:text-white truncate">
                    {{ $session['room_name'] }}
                </span>
                <span class="fi-in-text-item-label text-sm text-gray-500 dark:text-gray-400">
                    {{ $session['date'] }}
                </span>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                @if($session['attended'])
                    <span class="fi-in-text-item-label text-sm text-gray-500 dark:text-gray-400">
                        Активность: <span
                            class="font-semibold text-gray-950 dark:text-white">{{ $session['activity_score'] }}/10</span>
                    </span>
                @endif
                @if($session['attended'])
                    <x-filament::badge color="success">
                        Посетил
                    </x-filament::badge>
                @else
                    <x-filament::badge color="danger">
                        Пропустил
                    </x-filament::badge>
                @endif
            </div>
        </a>
    @empty
        <div class="fi-in-placeholder p-4 text-sm text-gray-400 dark:text-gray-500">
            Нет данных о посещениях
        </div>
    @endforelse
</div>