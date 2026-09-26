{{-- Строка материала: папка (открывается здесь же) или файл (открывается в новой вкладке, как в старом кабинете). --}}
@if ($item['folder'])
    <x-ui.row :href="$item['href']">
        <x-ui.file-tile icon="folder" />
        <x-ui.text :title="$item['title']" :sub="$item['meta']" />
    </x-ui.row>
@else
    <x-ui.row :href="$item['href']" target="_blank" rel="noopener">
        <x-ui.file-tile :name="$item['file']" />
        <x-ui.text :title="$item['title']" :sub="$item['meta']" />
    </x-ui.row>
@endif
