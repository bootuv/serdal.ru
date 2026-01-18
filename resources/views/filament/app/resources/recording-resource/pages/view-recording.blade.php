<x-filament-panels::page>
    <div class="space-y-6">
        @if($this->getVkEmbedUrl())
            <div class="bg-gray-900 rounded-xl shadow-lg overflow-hidden">
                <div class="relative w-full" style="padding-bottom: 56.25%;">
                    <iframe src="{{ $this->getVkEmbedUrl() }}" class="absolute inset-0 w-full h-full" frameborder="0"
                        allowfullscreen allow="autoplay; encrypted-media; fullscreen; picture-in-picture"></iframe>
                </div>
            </div>

            {{-- Recording Info --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Дата</p>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ $record->start_time?->format('d.m.Y H:i') ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Участники</p>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ $record->participants ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Длительность</p>
                        <p class="font-medium text-gray-900 dark:text-white">
                            @if($record->duration)
                                {{ gmdate('H:i:s', $record->duration) }}
                            @elseif($record->start_time && $record->end_time)
                                {{ gmdate('H:i:s', $record->start_time->diffInSeconds($record->end_time)) }}
                            @else
                                —
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm p-12 text-center">
                <div
                    class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-warning-100 dark:bg-warning-900/20 mb-4">
                    <x-heroicon-o-clock class="w-8 h-8 text-warning-600" />
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                    Видео ещё обрабатывается
                </h3>
                <p class="text-gray-500 dark:text-gray-400">
                    Запись загружается в VK Video. Обычно это занимает 1-2 минуты.
                </p>
            </div>
        @endif

        {{-- Back Button --}}
        <div>
            <a href="{{ \App\Filament\App\Resources\RecordingResource::getUrl() }}"
                class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                <x-heroicon-o-arrow-left class="w-4 h-4" />
                Назад к записям
            </a>
        </div>
    </div>
</x-filament-panels::page>