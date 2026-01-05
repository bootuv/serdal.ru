<div class="flex flex-col gap-2">
    @php
        $files = $getState();
        if (is_string($files)) {
            $decoded = json_decode($files, true);
            $files = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [$files];
        }
        $files = is_array($files) ? $files : [];

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    @endphp

    @foreach($files as $path)
        @if(is_string($path))
            @php
                $url = $path;
                try {
                    if (config('filesystems.default') === 's3' || \Illuminate\Support\Str::startsWith($path, ['homework-submissions/', 'homework-feedback/'])) {
                        $url = \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(30));
                    } else {
                        $url = \Illuminate\Support\Facades\Storage::url($path);
                    }
                } catch (\Exception $e) {
                    $url = \Illuminate\Support\Facades\Storage::url($path);
                }

                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $isImage = in_array($extension, $imageExtensions);
            @endphp

            <div class="flex items-center gap-2">
                <a href="{{ $url }}" target="_blank" class="text-primary-600 hover:underline flex items-center gap-1 flex-1">
                    @svg('heroicon-o-paper-clip', 'w-4 h-4')
                    {{ basename($path) }}
                </a>

                @if($isImage)
                    <button type="button" wire:click="$dispatch('openAnnotator', { imagePath: '{{ $path }}' })"
                        class="text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded text-gray-700 dark:text-gray-300 flex items-center gap-1"
                        title="Аннотировать">
                        @svg('heroicon-o-pencil', 'w-3 h-3')
                        Аннотировать
                    </button>
                @endif
            </div>
        @endif
    @endforeach
</div>