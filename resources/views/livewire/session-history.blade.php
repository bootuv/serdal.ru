<div>
    <x-filament::section :heading="'История сессий (' . $totalCount . ')'" collapsible collapsed>
        @if($sessions->isEmpty())
            <p class="text-gray-500 dark:text-gray-400 text-sm">Сессий пока не было</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap">Дата</th>
                            <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap">Статус
                            </th>
                            <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Длительность</th>
                            <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300 text-right whitespace-nowrap">
                                Участники</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($sessions as $session)
                            @php
                                $statusColor = match ($session->status) {
                                    'running' => 'success',
                                    'completed' => 'gray',
                                    default => 'warning',
                                };
                                $statusText = match ($session->status) {
                                    'running' => 'Активна',
                                    'completed' => 'Завершена',
                                    default => $session->status,
                                };
                                $duration = '';
                                if ($session->ended_at && $session->started_at) {
                                    $duration = $session->started_at->diffForHumans($session->ended_at, true);
                                } elseif ($session->started_at) {
                                    $duration = $session->started_at->diffForHumans(now(), true) . ' (активна)';
                                }
                            @endphp
                            @if($viewUrl)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer transition-colors"
                                    onclick="window.location.href='{{ str_replace(':id', $session->id, $viewUrl) }}'">
                            @else
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                @endif
                                <td class="px-4 py-3 text-gray-900 dark:text-white font-medium whitespace-nowrap">
                                    {{ $session->started_at->format('d.m.Y H:i') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <x-filament::badge :color="$statusColor" size="sm">
                                        {{ $statusText }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                    {{ $duration }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-right whitespace-nowrap">
                                    {{ $session->participant_count ?? 0 }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($hasMore)
                <div class="mt-4 flex justify-center">
                    <x-filament::button wire:click="loadMore" color="gray" size="sm" icon="heroicon-m-arrow-down">
                        Показать ещё
                    </x-filament::button>
                </div>
            @endif
        @endif
    </x-filament::section>
</div>