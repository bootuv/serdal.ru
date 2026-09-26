<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head :title="$homework->title" :sub="$facts" :back="$backUrl" back-label="Задания">
        <x-slot:actions>
            @if ($reviewUrl)
                <x-ui.btn variant="primary" :href="$reviewUrl">Проверить</x-ui.btn>
            @endif
            <x-ui.btn :href="$editUrl" class="hidden sm:inline-flex">Изменить</x-ui.btn>
            <x-ui.menu label="Действия с заданием">
                <x-ui.menu-item class="sm:hidden" x-on:click="window.location.href = '{{ $editUrl }}'">Изменить</x-ui.menu-item>
                <x-ui.menu-item wire:click="$set('confirmDelete', true)">Удалить задание</x-ui.menu-item>
            </x-ui.menu>
        </x-slot:actions>
    </x-ui.page-head>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        {{-- Фокус-блок: кто сдал и в каком состоянии работа --}}
        <x-ui.card focus class="min-w-0 lg:col-span-2" aria-labelledby="task-students">
            <x-ui.card-head id="task-students" title="Ученики">
                @if ($progress)<x-slot:action><span class="text-t2 text-muted">{{ $progress }}</span></x-slot:action>
                @endif
            </x-ui.card-head>

            @if ($rows->isEmpty())
                <p class="text-t2 text-muted">Ученики не выбраны — <a href="{{ $editUrl }}" class="link">добавьте их в задание</a>, чтобы они его увидели.</p>
            @else
                <x-ui.list>
                    @foreach ($rows as $r)
                        <x-ui.row :href="$r['url']" class="first:border-transparent first:pt-0" wire:key="st-{{ $r['id'] }}">
                            <x-ui.avatar :name="$r['name']" :id="$r['id']" />
                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="truncate text-t1 font-medium">{{ $r['name'] }}</span>
                                <span class="text-t2 text-muted">{{ $r['sub'] }}@if ($r['em']) · <x-ui.em>{{ $r['em'] }}</x-ui.em>@endif</span>
                            </div>
                            @if ($r['badge'])<x-ui.badge :tone="$r['badge'][0]">{{ $r['badge'][1] }}</x-ui.badge>@endif
                        </x-ui.row>
                    @endforeach
                </x-ui.list>
                @if ($more > 0)
                    <button type="button" wire:click="showMore" class="link self-start text-t2">Показать ещё {{ min($more, 30) }}</button>
                @endif
            @endif
        </x-ui.card>

        {{-- Условие задания --}}
        <x-ui.card class="min-w-0" aria-labelledby="task-body">
            <x-ui.card-head id="task-body" title="Задание" />
            @if ($description)
                <div class="rich break-words">{{ $description }}</div>
            @elseif (! $files)
                <p class="text-t2 text-muted">Без описания — <a href="{{ $editUrl }}" class="link">добавьте его</a>, если ученикам нужны подробности.</p>
            @endif
            @if ($files)
                <x-ui.list>
                    @foreach ($files as $file)
                        <x-ui.row :chevron="false" wire:key="tf-{{ $file['path'] }}">
                            <x-ui.file-tile :name="$file['path']" />
                            <x-ui.text :title="$file['name']" :sub="$file['meta']" />
                            <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="link text-t2">Открыть</a>
                        </x-ui.row>
                    @endforeach
                </x-ui.list>
            @endif
            <span class="text-t2 text-muted">{{ $issued }}</span>
        </x-ui.card>
    </div>

    @if ($confirmDelete)
        <x-ui.modal title="Удалить задание?" :sub="$homework->title" close="$set('confirmDelete', false)" width="s">
            <p class="text-t1">Задание пропадёт у учеников вместе с их сданными работами, оценками и файлами. Вернуть их не получится.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="$set('confirmDelete', false)">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">Удалить задание и работы</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
