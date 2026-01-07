@php
    $files = $getState();
    if (is_string($files)) {
        $decoded = json_decode($files, true);
        $files = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [$files];
    }
    $files = is_array($files) ? $files : [];

    // Get annotated files and submission ID from viewData
    $viewData = $getViewData();
    $annotatedFiles = $viewData['annotatedFiles'] ?? [];
    if (is_callable($annotatedFiles)) {
        $annotatedFiles = $annotatedFiles();
    }
    $annotatedFiles = is_array($annotatedFiles) ? $annotatedFiles : [];
    
    $showAnnotateButton = $viewData['showAnnotateButton'] ?? false;
    
    $submissionId = $viewData['submissionId'] ?? null;
    if (is_callable($submissionId)) {
        $submissionId = $submissionId();
    }
    
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
@endphp

<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
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
                $isAnnotated = in_array($path, $annotatedFiles);
                $filename = basename($path);
                // Shorten long filenames
                $displayName = strlen($filename) > 20 ? substr($filename, 0, 17) . '...' : $filename;
            @endphp
            
            <div class="relative group rounded-lg border overflow-hidden transition-colors
                {{ $isAnnotated 
                    ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800 hover:border-amber-400 dark:hover:border-amber-600' 
                    : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500' 
                }}">
                {{-- Thumbnail or icon --}}
                @if($isImage && $showAnnotateButton)
                    {{-- Images open in annotator when teacher can annotate --}}
                    <button 
                        type="button"
                        wire:click="$dispatch('openAnnotator', { imagePath: '{{ $path }}', submissionId: {{ $submissionId ?? 'null' }} })"
                        class="block aspect-square w-full cursor-pointer"
                    >
                        <img 
                            src="{{ $url }}" 
                            alt="{{ $filename }}"
                            class="w-full h-full object-cover"
                            loading="lazy"
                        >
                    </button>
                @elseif($isImage)
                    {{-- Students just see the image link --}}
                    <a href="{{ $url }}" target="_blank" class="block aspect-square">
                        <img 
                            src="{{ $url }}" 
                            alt="{{ $filename }}"
                            class="w-full h-full object-cover"
                            loading="lazy"
                        >
                    </a>
                @else
                    {{-- Non-image files --}}
                    <a href="{{ $url }}" target="_blank" class="block aspect-square">
                        <div class="w-full h-full flex items-center justify-center bg-gray-100 dark:bg-gray-700">
                            @switch($extension)
                                @case('pdf')
                                    @svg('heroicon-o-document-text', 'w-12 h-12 text-red-500')
                                    @break
                                @case('doc')
                                @case('docx')
                                    @svg('heroicon-o-document-text', 'w-12 h-12 text-blue-500')
                                    @break
                                @default
                                    @svg('heroicon-o-document', 'w-12 h-12 text-gray-400')
                            @endswitch
                        </div>
                    </a>
                @endif
                
                {{-- Filename & status --}}
                <div class="p-2">
                    <p class="text-xs text-gray-600 dark:text-gray-400 truncate" title="{{ $filename }}">
                        {{ $displayName }}
                    </p>
                    
                    @if($showAnnotateButton && $isImage)
                        {{-- Teacher view --}}
                        @if($isAnnotated)
                            <x-filament::badge color="warning" icon="heroicon-o-document-check" class="mt-1 w-full justify-center">
                                Аннотировано
                            </x-filament::badge>
                        @else
                            <x-filament::badge color="gray" icon="heroicon-o-pencil" class="mt-1 w-full justify-center">
                                Аннотировать
                            </x-filament::badge>
                        @endif
                    @elseif(!$showAnnotateButton && $isImage && $isAnnotated)
                        {{-- Student view - show annotation status --}}
                        <x-filament::badge color="warning" icon="heroicon-o-document-check" class="mt-1 w-full justify-center">
                            Аннотировано
                        </x-filament::badge>
                    @endif
                </div>
            </div>
        @endif
    @endforeach
</div>