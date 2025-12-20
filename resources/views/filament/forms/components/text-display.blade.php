<div class="mb-4">
    <div class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">
        {{ $getLabel() }}
    </div>
    <div class="text-base text-gray-900 dark:text-gray-100" style="white-space: pre-wrap;">
        {{ $getState() ?: '—' }}
    </div>
</div>