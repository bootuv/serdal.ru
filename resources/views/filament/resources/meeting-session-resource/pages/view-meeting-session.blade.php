<x-filament-panels::page>
    @if($record->analytics_data)
        {{-- Meeting Summary --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-users class="w-5 h-5 text-primary-500" />
                        Участники
                    </div>
                </x-slot>
                <div class="text-3xl font-bold">{{ $record->participant_count }}</div>
                <div class="text-sm text-gray-500">Всего пользователей</div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-microphone class="w-5 h-5 text-success-500" />
                        Голос
                    </div>
                </x-slot>
                <div class="text-3xl font-bold">{{ $record->analytics_data['voice_participant_count'] ?? 0 }}</div>
                <div class="text-sm text-gray-500">С микрофоном</div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-video-camera class="w-5 h-5 text-info-500" />
                        Видео
                    </div>
                </x-slot>
                <div class="text-3xl font-bold">{{ $record->analytics_data['video_count'] ?? 0 }}</div>
                <div class="text-sm text-gray-500">С камерой</div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-clock class="w-5 h-5 text-warning-500" />
                        Длительность
                    </div>
                </x-slot>
                <div class="text-3xl font-bold">
                    {{ $record->started_at->diff($record->ended_at)->format('%H:%I:%S') }}
                </div>
                <div class="text-sm text-gray-500">Время сессии</div>
            </x-filament::section>
        </div>

        {{-- Session Info --}}
        <x-filament::section class="mb-6">
            <x-slot name="heading">Информация о сессии</x-slot>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <div class="text-sm font-medium text-gray-500">Начало</div>
                    <div class="text-lg">{{ $record->started_at->format('d.m.Y H:i:s') }}</div>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500">Конец</div>
                    <div class="text-lg">{{ $record->ended_at?->format('d.m.Y H:i:s') ?? 'В процессе' }}</div>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500">Модераторы</div>
                    <div class="text-lg">{{ $record->analytics_data['moderator_count'] ?? 0 }}</div>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500">Слушатели</div>
                    <div class="text-lg">{{ $record->analytics_data['listener_count'] ?? 0 }}</div>
                </div>
            </div>
        </x-filament::section>

        {{-- Participants Table --}}
        @if(isset($record->analytics_data['participants']) && count($record->analytics_data['participants']) > 0)
            <x-filament::section>
                <x-slot name="heading">Список участников</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Имя
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Роль
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Микрофон</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Камера</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Презентер</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            @foreach($record->analytics_data['participants'] as $participant)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $participant['full_name'] }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                                    {{ $participant['role'] === 'MODERATOR' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $participant['role'] === 'MODERATOR' ? 'Модератор' : 'Участник' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        @if($participant['has_joined_voice'])
                                            <x-heroicon-o-check-circle class="w-5 h-5 text-success-500 inline" />
                                        @else
                                            <x-heroicon-o-x-circle class="w-5 h-5 text-gray-400 inline" />
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        @if($participant['has_video'])
                                            <x-heroicon-o-check-circle class="w-5 h-5 text-success-500 inline" />
                                        @else
                                            <x-heroicon-o-x-circle class="w-5 h-5 text-gray-400 inline" />
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        @if($participant['is_presenter'])
                                            <x-heroicon-o-check-circle class="w-5 h-5 text-success-500 inline" />
                                        @else
                                            <x-heroicon-o-x-circle class="w-5 h-5 text-gray-400 inline" />
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    @else
        <x-filament::section>
            <div class="text-center py-12">
                <x-heroicon-o-information-circle class="w-16 h-16 text-gray-400 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                    Аналитика недоступна
                </h3>
                <p class="text-gray-500">
                    Данные о сессии не были сохранены. Это может произойти, если сессия была завершена до внедрения системы
                    аналитики.
                </p>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>