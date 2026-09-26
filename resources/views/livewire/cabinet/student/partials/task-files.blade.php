{{-- Файлы работы или комментария на мятном фокус-блоке: белые плитки, по клику — открыть/скачать. --}}
@if ($files)
    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        @foreach ($files as $file)
            <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="group flex items-center gap-3 rounded-lg bg-white p-3" wire:key="file-{{ $file['path'] }}">
                <x-ui.file-tile :name="$file['path']" onMint />
                <span class="flex min-w-0 flex-1 flex-col gap-1">
                    <span class="truncate text-t1 font-medium">{{ $file['name'] }}</span>
                    <span class="truncate text-t2 text-muted">@if ($file['annotated'])<x-ui.em>С пометками учителя</x-ui.em>@else{{ $file['meta'] }}@endif</span>
                </span>
                <x-ui.icon name="download" class="text-muted group-hover:text-ink" />
            </a>
        @endforeach
    </div>
@endif
