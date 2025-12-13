<x-filament::page>
    @php
        $analytics = $record->analytics_data;
        $participants = $analytics['participants'] ?? [];

        // Convert to collection for easier sorting/filtering if needed
        $participants = collect($participants);
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-filament::section>
            <div class="text-xl font-bold">{{ $analytics['participant_count'] ?? 0 }}</div>
            <div class="text-sm text-gray-500">Всего участников</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xl font-bold">{{ gmdate("H:i:s", $record->duration_seconds ?? 0) }}</div>
            <div class="text-sm text-gray-500">Длительность</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xl font-bold">{{ $analytics['poll_count'] ?? 0 }}</div>
            <div class="text-sm text-gray-500">Голосования</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xl font-bold">{{ $analytics['message_count'] ?? 0 }}</div>
            <!-- Aggregated if tracked globally, else sum participants -->
            <div class="text-sm text-gray-500">Сообщения</div>
        </x-filament::section>
    </div>

    <x-filament::section heading="Обзор участников">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-6 py-3">Пользователь</th>
                        <th scope="col" class="px-6 py-3">Роль</th>
                        <th scope="col" class="px-6 py-3">Время в сети</th>
                        <th scope="col" class="px-6 py-3">Микрофон</th>
                        <th scope="col" class="px-6 py-3">Вебкамера</th>
                        <th scope="col" class="px-6 py-3">Сообщения</th>
                        <th scope="col" class="px-6 py-3">Реакции</th>
                        <th scope="col" class="px-6 py-3">Рука</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($participants as $p)
                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                            <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                {{ $p['full_name'] ?? 'Unknown' }}
                            </td>
                            <td class="px-6 py-4">
                                <x-filament::badge :color="$p['role'] === 'MODERATOR' ? 'success' : 'gray'">
                                    {{ $p['role'] }}
                                </x-filament::badge>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $joined = isset($p['joined_at']) ? \Carbon\Carbon::parse($p['joined_at']) : null;
                                    $left = isset($p['left_at']) ? \Carbon\Carbon::parse($p['left_at']) : ($record->ended_at ?? now());
                                    $duration = $joined ? $left->diffInSeconds($joined) : 0;
                                @endphp
                                {{ gmdate("H:i:s", abs($duration)) }}
                            </td>
                            <td class="px-6 py-4">
                                {{ gmdate("H:i:s", $p['talking_time'] ?? 0) }}
                            </td>
                            <td class="px-6 py-4">
                                {{ gmdate("H:i:s", $p['webcam_time'] ?? 0) }}
                            </td>
                            <td class="px-6 py-4">
                                {{ $p['message_count'] ?? 0 }}
                            </td>
                            <td class="px-6 py-4">
                                {{ $p['emoji_count'] ?? 0 }}
                            </td>
                            <td class="px-6 py-4">
                                {{ $p['raise_hand_count'] ?? 0 }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center">Нет участников</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament::page>