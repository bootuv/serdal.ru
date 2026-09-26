{{-- Строка материала: папка (открывается здесь же) или файл (открывается в новой вкладке, как в старом кабинете). --}}
@if ($item['folder'])
    <x-ui.row :href="$item['href']">
        <x-ui.file-tile icon="folder" />
        <x-ui.text :title="$item['title']" :sub="$item['meta']" />
    </x-ui.row>
@else
    <x-ui.row :href="$item['href']" target="_blank" rel="noopener">
        @if ($item['thumb'] ?? null)
            <img src="{{ $item['thumb'] }}" alt="" loading="lazy" class="size-10 shrink-0 rounded bg-soft object-cover">
        @else
            <x-ui.file-tile :name="$item['file']" />
        @endif
        <x-ui.text :title="$item['title']" :sub="$item['meta']" />
    </x-ui.row>
@endif
