<div>
    @php
        $homework = $getRecord();
        $students = $homework->students;
        $submissions = $homework->submissions->keyBy('student_id');
    @endphp

    @if($students->isEmpty())
        <div class="text-sm text-gray-500 dark:text-gray-400 py-4 text-center">
            Нет назначенных учеников
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Ученик</th>
                        <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Статус</th>
                        <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Сдано</th>
                        <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Оценка</th>
                        <th class="text-right py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($students as $student)
                        @php
                            $submission = $submissions->get($student->id);
                            $status = 'pending';
                            $statusLabel = 'Не сдано';
                            $statusColor = 'gray';

                            if ($submission) {
                                if ($submission->grade !== null) {
                                    $status = 'graded';
                                    $statusLabel = 'Оценено';
                                    $statusColor = 'success';
                                } elseif ($submission->submitted_at) {
                                    $status = 'submitted';
                                    $statusLabel = 'На проверке';
                                    $statusColor = 'warning';
                                }
                            }
                        @endphp
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-2">
                                    <img src="{{ $student->avatar_url }}" alt="" class="w-8 h-8 rounded-full object-cover">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $student->name }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-3">
                                <x-filament::badge :color="$statusColor">
                                    {{ $statusLabel }}
                                </x-filament::badge>
                            </td>
                            <td class="py-3 px-3 text-gray-600 dark:text-gray-400">
                                {{ format_datetime($submission?->submitted_at) ?? '—' }}
                            </td>
                            <td class="py-3 px-3">
                                @if($submission?->grade !== null)
                                    <span class="font-semibold text-lg text-primary-600 dark:text-primary-400">
                                        {{ $submission->grade }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-right">
                                @if($submission && $submission->submitted_at)
                                    <x-filament::button size="sm" color="primary" tag="a"
                                        href="{{ route('filament.app.resources.homework-submissions.view', $submission) }}">
                                        {{ $submission->grade !== null ? 'Просмотр' : 'Проверить' }}
                                    </x-filament::button>
                                @else
                                    <span class="text-gray-400 text-xs">Ожидает сдачи</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>