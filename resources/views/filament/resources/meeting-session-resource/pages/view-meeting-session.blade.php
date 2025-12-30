<x-filament::page>
    @php
        $analytics = $record->analytics_data;
        $participants = $analytics['participants'] ?? [];

        // Convert to collection for easier sorting/filtering if needed
        $participants = collect($participants);

        // Filter out Teachers (Tutors and Mentors) and get Users with Avatars
        $userIds = $participants->pluck('user_id')->filter();
        
        // Fetch users to check roles AND get avatars
        $users = \App\Models\User::whereIn('id', $userIds)->get()->keyBy('id');
        
        $teacherIds = $users->filter(fn($u) => in_array($u->role, [\App\Models\User::ROLE_TUTOR, \App\Models\User::ROLE_MENTOR]))
            ->pluck('id')
            ->map(fn($id) => (string)$id)
            ->toArray();
            
        $participants = $participants->reject(function ($p) use ($teacherIds) {
            return in_array((string)($p['user_id'] ?? ''), $teacherIds, true);
        });
        
        $stats = $record->getStudentAttendance();
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-filament::section>
            <div class="text-xl font-bold">{{ $record->started_at?->format('d.m.Y H:i') ?? '-' }}</div>
            <div class="text-sm text-gray-500">Начало</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xl font-bold">{{ $record->ended_at?->format('d.m.Y H:i') ?? '-' }}</div>
            <div class="text-sm text-gray-500">Конец</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xl font-bold">{{ gmdate("H:i:s", $record->duration_seconds ?? 0) }}</div>
            <div class="text-sm text-gray-500">Длительность</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xl font-bold" style="color: {{ $stats['color'] }}">
                {{ $stats['attended'] }}/{{ $stats['total'] }}
            </div>
            <div class="text-sm text-gray-500">Всего участников</div>
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
                        <th scope="col" class="px-6 py-3">Участники</th>

                        <th scope="col" class="px-6 py-3">Время в сети</th>
                        <th scope="col" class="px-6 py-3">Микрофон</th>
                        <th scope="col" class="px-6 py-3">Вебкамера</th>
                        <th scope="col" class="px-6 py-3">Сообщения</th>
                        <th scope="col" class="px-6 py-3">Реакции</th>
                        <th scope="col" class="px-6 py-3">Рука</th>
                        <th scope="col" class="px-6 py-3">Оценка активности (0-10)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($participants as $p)
                        @php
                            // Calculate Activity Score
                            // Logic: (Talk Time (m) * 2) + (Messages * 1) + (Emoji * 1) + (Raise Hand * 2)
                            // Normalized to 0-10 range roughly? Or just raw score.
                            // Let's assume max reasonable score is 20 for normalization, or just show raw.
                            // User asked for "Activity Score", likely 0-10 or similar.
                            $talkMinutes = ($p['talking_time'] ?? 0) / 60;
                            $rawScore = ($talkMinutes * 2) + ($p['message_count'] ?? 0) + ($p['emoji_count'] ?? 0) + (($p['raise_hand_count'] ?? 0) * 2);
                            $score = min(10, round($rawScore)); 
                            
                            $user = $users[$p['user_id'] ?? ''] ?? null;
                            $avatar = $user?->avatar_url ?? asset('images/default-avatar.png'); 
                        @endphp
                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                            <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white flex items-center gap-2">
                                <img src="{{ $avatar }}" alt="Avatar" class="w-8 h-8 rounded-full bg-gray-200">
                                <span>{{ $p['full_name'] ?? 'Unknown' }}</span>
                            </td>

                            <td class="px-6 py-4">
                                @php
                                    $joined = isset($p['joined_at']) ? \Carbon\Carbon::parse($p['joined_at']) : null;
                                    $left = isset($p['left_at']) ? \Carbon\Carbon::parse($p['left_at']) : ($record->ended_at ?? now());
                                    $duration = $joined ? $left->diffInSeconds($joined) : 0;
                                @endphp
                                <div class="flex items-center">
                                    <div class="h-2.5 w-2.5 rounded-full bg-green-500 me-2"></div>
                                    {{ gmdate("H:i:s", abs($duration)) }}
                                </div>
                                <div class="text-xs text-gray-400">
                                    {{ $joined ? $joined->format('H:i') : '-' }} - {{ $left ? $left->format('H:i') : '-' }}
                                </div>
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
                            <td class="px-6 py-4 font-bold text-center">
                                {{ $score }}/10
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-4 text-center">Нет участников</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    @if(!empty($analytics['timeline']))
        <x-filament::section heading="Шкала времени (События)">
            <div class="space-y-4">
                @foreach($analytics['timeline'] as $event)
                    <div class="flex items-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="text-sm text-gray-500 w-24">
                            {{ \Carbon\Carbon::parse($event['timestamp'])->format('H:i:s') }}
                        </div>
                        <div class="font-medium">
                            {{ $event['description'] ?? $event['type'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament::page>