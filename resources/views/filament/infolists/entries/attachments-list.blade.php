<div class="flex flex-col gap-1">
    @php
        $files = $getState();
        if (is_string($files)) {
            $decoded = json_decode($files, true);
            $files = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [$files];
        }
        $files = is_array($files) ? $files : [];
    @endphp

    @foreach($files as $path)
        @if(is_string($path))
            <a href="{{ \Storage::url($path) }}" target="_blank"
                class="text-primary-600 hover:underline flex items-center gap-1">
                @svg('heroicon-o-paper-clip', 'w-4 h-4')
                {{ basename($path) }}
            </a>
        @endif
    @endforeach
</div>