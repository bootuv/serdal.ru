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
                            @if(auth()->user()->role === \App\Models\User::ROLE_STUDENT)
                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                    Посещаемость</th>
                            @endif
                            <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Длительность</th>
                            @if(auth()->user()->role !== \App\Models\User::ROLE_STUDENT)
                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Стоимость</th>
                            @endif
                            <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300 text-right whitespace-nowrap">
                                Участники</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($sessions as $session)
                            @php

                                $duration = '';
                                if ($session->ended_at && $session->started_at) {
                                    $duration = $session->started_at->diffForHumans($session->ended_at, true);
                                } elseif ($session->started_at) {
                                    $duration = $session->started_at->diffForHumans(now(), true) . ' (активна)';
                                }
                                $isAttended = false;
                                if (auth()->user()->role === \App\Models\User::ROLE_STUDENT) {
                                    $participants = $session->analytics_data['participants'] ?? [];
                                    foreach ($participants as $p) {
                                        if (($p['user_id'] ?? '') === (string) auth()->user()->id) { // Ensure comparison as strings if needed, though int safe usually
                                            $isAttended = true;
                                            break;
                                        }
                                    }
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
                                @if(auth()->user()->role === \App\Models\User::ROLE_STUDENT)
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex justify-start items-center gap-1.5">
                                            @if($isAttended)
                                                <svg class="w-4 h-4 text-success-600 dark:text-success-500"
                                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd"
                                                        d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                                <span class="text-sm font-medium text-success-600 dark:text-success-500">Посетил</span>
                                            @else
                                                <svg class="w-4 h-4 text-danger-600 dark:text-danger-500"
                                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path
                                                        d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                                </svg>
                                                <span class="text-sm font-medium text-danger-600 dark:text-danger-500">Пропустил</span>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                    {{ $duration }}
                                </td>
                                @if(auth()->user()->role !== \App\Models\User::ROLE_STUDENT)
                                    @php
                                        // Use stored pricing snapshot if available (immutable historical data)
                                        $sessionCost = 0;
                                        if (isset($session->pricing_snapshot['total_cost'])) {
                                            $sessionCost = $session->pricing_snapshot['total_cost'];
                                        } else {
                                            // Fallback to dynamic calculation for old sessions without snapshot
                                            $room = $session->room;
                                            if ($room) {
                                                $lessonType = $room->user?->lessonTypes?->where('type', $room->type)->first();
                                                $paymentType = $lessonType?->payment_type ?? 'per_lesson';
                                                if ($paymentType === 'monthly') {
                                                    foreach ($room->participants as $participant) {
                                                        $sessionCost += $room->getEffectivePrice($participant->id) ?? 0;
                                                    }
                                                } else {
                                                    $analytics = $session->analytics_data ?? [];
                                                    $participantsData = $analytics['participants'] ?? [];
                                                    $attendedIds = collect($participantsData)->pluck('user_id')->map(fn($id) => (string) $id)->toArray();
                                                    foreach ($room->participants as $participant) {
                                                        if (in_array((string) $participant->id, $attendedIds)) {
                                                            $sessionCost += $room->getEffectivePrice($participant->id) ?? 0;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    @endphp
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        {{ number_format($sessionCost, 0, '.', ' ') }} ₽
                                    </td>
                                @endif
                                @php
                                    $stats = $session->getStudentAttendance();
                                    $color = $stats['color'];
                                    // Calculate a lighter background color based on the main color (simple opacity approach or hardcoded mapping if needed, but hex with opacity works for modern browsers, or we can just set text color and a standard light bg?)
                                    // User asked for "inline styles". Let's try to set style directly.
                                    // Using rgba for background to make it lighter.
                                    // Converting hex to rgb manually or simply using a consistent look.
                                    // Simple approach: Use the color for text and a very light standard bg, or try to apply the color to bg with opacity.
                                    // Actually, let's use the pure hex for text and a 10% opacity version for background.
                                @endphp
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-right whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center justify-center -my-1 mx-auto min-h-6 min-w-6 px-2 py-0.5 rounded-full text-xs font-medium"
                                        style="color: {{ $color }}; background-color: {{ $color }}1A;">
                                        <!-- 1A is ~10% opacity in hex -->
                                        {{ $stats['attended'] }}/{{ $stats['total'] }}
                                    </span>
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