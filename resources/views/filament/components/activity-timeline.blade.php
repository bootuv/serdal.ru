@props([
    'activities' => [],
])

@if(count($activities) > 0)
    <div class="space-y-4">
        @foreach($activities as $activity)
            <div class="flex items-start gap-3">
                {{-- Icon --}}
                <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center
                    @switch($activity->color)
                        @case('primary')
                            bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400
                            @break
                        @case('success')
                            bg-success-100 dark:bg-success-900/30 text-success-600 dark:text-success-400
                            @break
                        @case('warning')
                            bg-warning-100 dark:bg-warning-900/30 text-warning-600 dark:text-warning-400
                            @break
                        @case('danger')
                            bg-danger-100 dark:bg-danger-900/30 text-danger-600 dark:text-danger-400
                            @break
                        @default
                            bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400
                    @endswitch
                ">
                    @svg($activity->icon, 'w-4 h-4')
                </div>
                
                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $activity->label }}
                    </p>
                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ format_datetime($activity->created_at) }}</span>
                        @if($activity->user)
                            <span>•</span>
                            <span>{{ $activity->user->name }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
        Нет записей
    </p>
@endif
