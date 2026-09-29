{{-- Вкладка «Справочники»: предметы и направления (макет AdminDictionaries). --}}
<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <x-ui.seg fit :items="['subjects' => 'Предметы', 'directs' => 'Направления']" model="dict" :active="$dict" aria-label="Справочник" />
        <x-ui.search wire:model.live.debounce.300ms="dictQ" :placeholder="$words['search']" />
    </div>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <x-ui.card class="gap-0 lg:col-span-2" aria-label="{{ $dict === 'directs' ? 'Направления' : 'Предметы' }}">
            <div class="mb-4 flex items-center justify-between gap-4">
                <x-ui.seg fit :items="['all' => 'Все · ' . $allCount, 'unused' => 'Не используются · ' . $unusedCount]" model="dictFilter" :active="$dictFilter" aria-label="Что показать" />
            </div>

            <x-ui.list>
                @if ($adding)
                    <div class="flex flex-col gap-2 border-t border-line py-3">
                        <div class="flex items-center gap-2">
                            <input type="text" wire:model="addName" wire:keydown.enter.prevent="addItem" wire:keydown.escape="cancelAdd" placeholder="{{ $words['ph'] }}" aria-label="{{ $words['ph'] }}" autofocus
                                   @class(['field min-w-0 flex-1', 'shadow-outline-ink' => $errors->has('addName')])>
                            <x-ui.btn size="s" wire:click="addItem">Добавить</x-ui.btn>
                            <button type="button" class="link px-2 text-t2" wire:click="cancelAdd">Отмена</button>
                        </div>
                        @error('addName')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                    </div>
                @endif

                @foreach ($rows as $r)
                    @if ($editId === $r['id'])
                        <div wire:key="dict-edit-{{ $r['id'] }}" class="flex flex-col gap-2 border-t border-line py-3">
                            <div class="flex items-center gap-2">
                                <input type="text" wire:model="editName" wire:keydown.enter.prevent="saveRename" wire:keydown.escape="cancelRename" aria-label="Новое название" autofocus
                                       @class(['field min-w-0 flex-1', 'shadow-outline-ink' => $errors->has('editName')])>
                                <x-ui.btn size="s" wire:click="saveRename">Сохранить</x-ui.btn>
                                <button type="button" class="link px-2 text-t2" wire:click="cancelRename">Отмена</button>
                            </div>
                            @error('editName')
                                <span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>
                            @else
                                <span class="text-t3 text-muted">{{ $r['teachers'] ? 'Название поменяется у ' . plural_ru($r['teachers'], 'учителя', 'учителей', 'учителей') . ' и на сайте' : 'Название поменяется на сайте' }}</span>
                            @enderror
                        </div>
                    @else
                        <div wire:key="dict-{{ $r['id'] }}" class="flex min-h-9 items-center gap-4 border-t border-line py-2 last:pb-0">
                            @if ($dict === 'subjects')
                                <button type="button" class="shrink-0 rounded-full" wire:click="openIcon({{ $r['id'] }})" aria-label="Значок и цвет: {{ $r['name'] }}" title="Значок и цвет">{!! \App\Support\SubjectIcons::badge($r['name'], $r['icon'], $r['color'], 28) !!}</button>
                            @endif
                            <span class="min-w-0 flex-1 truncate text-t1-s font-medium">{{ $r['name'] }}</span>
                            <span class="shrink-0 text-right text-t2 text-muted">{{ \App\Livewire\Cabinet\Admin\Settings::used($r) }}</span>
                            <x-ui.menu label="Действия: {{ $r['name'] }}">
                                <x-ui.menu-item wire:click="startRename({{ $r['id'] }})">Переименовать</x-ui.menu-item>
                                @if ($dict === 'subjects')
                                    <x-ui.menu-item wire:click="openIcon({{ $r['id'] }})">Значок и цвет</x-ui.menu-item>
                                @endif
                                <x-ui.menu-item wire:click="openMerge({{ $r['id'] }})">Объединить с…</x-ui.menu-item>
                                @if ($r['teachers'] === 0 && $r['applications'] === 0)
                                    <x-ui.menu-item wire:click="deleteItem({{ $r['id'] }})">Удалить</x-ui.menu-item>
                                @else
                                    <x-ui.menu-item wire:click="openMerge({{ $r['id'] }})" class="text-muted" title="Используется — сначала объедините с другим">Удалить — сначала объедините</x-ui.menu-item>
                                @endif
                            </x-ui.menu>
                        </div>
                    @endif
                @endforeach
            </x-ui.list>

            @if ($rows->isEmpty() && ! $adding)
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <span class="text-t2 text-muted">{{ trim($dictQ) !== '' ? 'Ничего не нашлось — проверьте написание' : ($dictFilter === 'unused' ? 'Все используются — удалять нечего' : 'Пока пусто — нажмите «Добавить»') }}</span>
                    @if (trim($dictQ) !== '' || $dictFilter === 'unused')
                        <x-ui.btn size="s" wire:click="resetDictView" class="self-start lg:self-auto">Показать все</x-ui.btn>
                    @endif
                </div>
            @endif
        </x-ui.card>

        {{-- Похожие названия — фокус-блок --}}
        <x-ui.card focus aria-labelledby="d-similar">
            <x-ui.card-head id="d-similar" title="Похожие названия" />
            @if ($pairs === [])
                <span class="text-t2 text-muted">Похожих названий нет</span>
            @else
                <x-ui.list>
                    @foreach ($pairs as $p)
                        <div wire:key="pair-{{ $p['source']['id'] }}-{{ $p['target']['id'] }}" class="flex flex-col gap-3 border-t border-line py-4 last:pb-0">
                            <div class="flex min-w-0 flex-col gap-1">
                                <span class="text-t1-s font-medium">{{ $p['source']['name'] }} и {{ $p['target']['name'] }}</span>
                                <span class="text-t2 text-muted">учителей: {{ $p['source']['teachers'] }} и {{ $p['target']['teachers'] }}</span>
                            </div>
                            <x-ui.btn size="s" wire:click="openMerge({{ $p['source']['id'] }}, {{ $p['target']['id'] }})" class="self-start">Объединить</x-ui.btn>
                        </div>
                    @endforeach
                </x-ui.list>
            @endif
        </x-ui.card>
    </div>

    @if ($iconItem)
        <x-ui.modal :title="'Значок «' . $iconItem['name'] . '»'" sub="Виден в каталоге на сайте" close="closeIcon">
            <div class="flex items-center gap-4">
                {!! \App\Support\SubjectIcons::badge($iconItem['name'], $iconMark, $iconColor ?: null, 48) !!}
                <span class="text-t2 text-muted">{{ $iconPick === '' && $iconColor === '' ? 'Подобран по названию' : 'Выбран вручную' }}</span>
            </div>

            <div class="flex flex-col gap-2">
                <span id="ic-l" class="text-t2 font-medium">Значок</span>
                <div class="flex flex-wrap gap-2" role="group" aria-labelledby="ic-l">
                    <x-ui.swatch wide :on="$iconPick === ''" wire:click="pickIcon('')">Авто</x-ui.swatch>
                    @foreach (\App\Support\SubjectIcons::ICONS as $key => $label)
                        <x-ui.swatch :on="$iconPick === $key" wire:click="pickIcon('{{ $key }}')" wire:key="ic-{{ $key }}" aria-label="{{ $label }}" title="{{ $label }}">{!! \App\Support\SubjectIcons::badge($iconItem['name'], $key, $iconColor ?: null, 32) !!}</x-ui.swatch>
                    @endforeach
                    <x-ui.swatch wide :on="$iconPick === 'text'" wire:click="pickIcon('text')">Буква</x-ui.swatch>
                </div>
            </div>
            @if ($iconPick === 'text')
                <x-ui.field label="Буква или две" name="iconText" wire:model.live.debounce.300ms="iconText" maxlength="{{ \App\Support\SubjectIcons::TEXT_MAX }}" placeholder="Аа" hint="Для языков: Аа, Ab, ع" autofocus />
            @endif

            <div class="flex flex-col gap-2">
                <span id="cl-l" class="text-t2 font-medium">Цвет</span>
                <div class="flex flex-wrap gap-2" role="group" aria-labelledby="cl-l">
                    <x-ui.swatch wide :on="$iconColor === ''" wire:click="pickColor('')">Авто</x-ui.swatch>
                    @foreach (\App\Support\SubjectIcons::COLORS as $key => [$bg, $fg, $label])
                        <x-ui.swatch :on="$iconColor === $key" wire:click="pickColor('{{ $key }}')" wire:key="cl-{{ $key }}" aria-label="{{ $label }}" title="{{ $label }}">{!! \App\Support\SubjectIcons::badge($iconItem['name'], $iconMark, $key, 32) !!}</x-ui.swatch>
                    @endforeach
                </div>
            </div>
            <x-slot:footer>
                <x-ui.btn wire:click="closeIcon">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="saveIcon">Сохранить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($mergeSourceItem)
        <x-ui.modal :title="'Объединить «' . $mergeSourceItem['name'] . '»'" :sub="\App\Livewire\Cabinet\Admin\Settings::used($mergeSourceItem)" close="closeMerge">
            <div class="flex flex-col gap-2">
                <span id="mg-l" class="text-t2 font-medium">С чем объединить</span>
                <x-ui.search full wire:model.live.debounce.300ms="mergeQ" placeholder="Найти по названию" />
                <div class="flex flex-col gap-2" role="radiogroup" aria-labelledby="mg-l">
                    @forelse ($mergeOptions as $o)
                        <x-ui.option type="radio" name="mergeTarget" value="{{ $o['id'] }}" wire:model.live="mergeTarget" wire:key="mg-{{ $o['id'] }}" :title="$o['name']" :sub="'учителей: ' . $o['teachers']" />
                    @empty
                        <span class="text-t2 text-muted">Ничего не нашлось</span>
                    @endforelse
                </div>
            </div>
            @if ($mergeText)
                <p class="text-t1-s">{{ $mergeText['lead'] }}<span class="font-semibold">{{ $mergeText['target'] }}</span>, а <span class="font-semibold">{{ $mergeText['source'] }}</span> удалится.</p>
            @endif
            <x-slot:footer>
                <x-ui.btn wire:click="closeMerge">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="merge" :disabled="! $mergeText">Объединить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
