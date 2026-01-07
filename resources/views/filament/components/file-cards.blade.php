@props([
    'files' => [],
    'annotatedFiles' => [],
    'showAnnotateButton' => false,
    'submissionId' => null,
])

@php
    $files = is_string($files) ? json_decode($files, true) : $files;
    $files = is_array($files) ? $files : [];
    $annotatedFiles = is_array($annotatedFiles) ? $annotatedFiles : [];
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
            
            <div class="relative group rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden bg-gray-50 dark:bg-gray-800 hover:border-primary-500 dark:hover:border-primary-500 transition-colors">
                {{-- Thumbnail or icon --}}
                <a href="{{ $url }}" target="_blank" class="block aspect-square">
                    @if($isImage)
                        <img 
                            src="{{ $url }}" 
                            alt="{{ $filename }}"
                            class="w-full h-full object-cover"
                            loading="lazy"
                        >
                    @else
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
                    @endif
                </a>
                
                {{-- Annotation badge --}}
                @if($isAnnotated)
                    <div class="absolute top-2 right-2">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                            @svg('heroicon-s-pencil', 'w-3 h-3')
                        </span>
                    </div>
                @endif
                
                {{-- Filename & actions --}}
                <div class="p-2">
                    <p class="text-xs text-gray-600 dark:text-gray-400 truncate" title="{{ $filename }}">
                        {{ $displayName }}
                    </p>
                    
                    @if($showAnnotateButton && $isImage)
                        <button 
                            type="button"
                            wire:click="$dispatch('openAnnotator', { imagePath: '{{ $path }}', submissionId: {{ $submissionId ?? 'null' }} })"
                            class="mt-1 w-full text-xs px-2 py-1 bg-gray-100 hover:bg-primary-100 dark:bg-gray-700 dark:hover:bg-primary-900 rounded text-gray-700 dark:text-gray-300 flex items-center justify-center gap-1 transition-colors"
                        >
                            @svg('heroicon-o-pencil', 'w-3 h-3')
                            {{ $isAnnotated ? 'Редактировать' : 'Аннотировать' }}
                        </button>
                    @endif
                </div>
            </div>
        @endif
    @endforeach
</div>
