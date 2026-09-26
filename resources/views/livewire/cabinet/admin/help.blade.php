<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="База знаний" :sub="$facts">
        <x-slot:actions>
            <div class="hidden items-center gap-2 lg:flex">
                <x-ui.btn icon="folder" wire:click="newCategory">Новая категория</x-ui.btn>
                <x-ui.btn variant="dark" icon="plus" :href="route('cabinet.admin.help-article', ['article' => 'new'])">Новая статья</x-ui.btn>
            </div>
        </x-slot:actions>
    </x-ui.page-head>

    {{-- Телефон: действия шапки под заголовком --}}
    <div class="flex flex-wrap items-center gap-2 lg:hidden">
        <x-ui.btn variant="dark" icon="plus" :href="route('cabinet.admin.help-article', ['article' => 'new'])">Новая статья</x-ui.btn>
        <x-ui.btn icon="folder" wire:click="newCategory">Новая категория</x-ui.btn>
    </div>

    <div class="flex flex-col gap-6">
        {{-- Вкладки и поиск в одной строке с общей линией снизу --}}
        <div class="flex flex-col-reverse gap-4 lg:flex-row lg:items-end lg:gap-0">
            <x-ui.tabs :items="$tabs" model="tab" :active="$tab" class="lg:flex-1" />
            <div class="lg:border-b lg:border-line lg:pb-2 lg:pl-4">
                <x-ui.search wire:model.live.debounce.300ms="q" placeholder="Поиск по заголовкам" />
            </div>
        </div>

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
            {{-- Категории и статьи. Перетаскивание: категория — к краю соседней категории; статья — к соседней статье или на шапку категории --}}
            <x-ui.card class="gap-0 lg:col-span-2" aria-label="Категории и статьи" x-data="{ drag: null }">
                @forelse ($groups as $g)
                    <div wire:key="cat-{{ $g['id'] }}" class="flex flex-col {{ $loop->first ? '' : 'pt-8' }}">
                        <div draggable="{{ $searching ? 'false' : 'true' }}"
                             x-data="{ side: null }"
                             x-on:dragstart="drag = { type: 'c', id: {{ $g['id'] }} }"
                             x-on:dragend="drag = null"
                             x-on:dragover="if (@js($searching) || ! drag || (drag.type === 'c' && drag.id === {{ $g['id'] }})) { side = null; return }
                                 $event.preventDefault();
                                 if (drag.type === 'a') { side = 'in'; return }
                                 const r = $el.getBoundingClientRect(); side = ($event.clientY - r.top) / r.height < 0.5 ? 'before' : 'after'"
                             x-on:dragleave="side = null"
                             x-on:drop="if (drag && side) { $event.preventDefault(); $event.stopPropagation();
                                 drag.type === 'a' ? $wire.moveArticleToCategory(drag.id, {{ $g['id'] }}) : $wire.moveCategory(drag.id, {{ $g['id'] }}, side === 'before');
                                 side = null; drag = null }"
                             :class="side === 'in' && 'bg-soft'"
                             class="relative flex min-h-9 items-center gap-3 rounded-sm pb-3">
                            <span x-show="side === 'before'" x-cloak class="pointer-events-none absolute inset-x-0 top-0 border-t-2 border-ink"></span>
                            <span x-show="side === 'after'" x-cloak class="pointer-events-none absolute inset-x-0 bottom-0 border-b-2 border-ink"></span>
                            @unless ($searching)<x-ui.icon name="grip" size="s" class="cursor-grab text-faint" />@endunless
                            @if (\App\Support\HelpIcons::isKey($g['icon']))
                                <x-ui.icon :name="$g['icon']" />
                            @elseif (filled($g['icon']))
                                <span class="shrink-0" aria-hidden="true">{{ $g['icon'] }}</span>
                            @endif
                            <span class="truncate text-t1 font-semibold">{{ $g['name'] }}</span>
                            <span class="hidden shrink-0 text-t2 text-muted lg:inline">{{ $g['count'] }}</span>
                            @if ($g['hidden'])<x-ui.badge>Скрыта</x-ui.badge>@endif
                            <x-ui.menu class="ml-auto" label="Действия с категорией «{{ $g['name'] }}»">
                                <x-ui.menu-item wire:click="editCategory({{ $g['id'] }})">Изменить</x-ui.menu-item>
                                <x-ui.menu-item wire:click="toggleCategory({{ $g['id'] }})">{{ $g['hidden'] ? 'Показать на сайте' : 'Скрыть с сайта' }}</x-ui.menu-item>
                                <x-ui.menu-item wire:click="askDeleteCategory({{ $g['id'] }})">Удалить</x-ui.menu-item>
                            </x-ui.menu>
                        </div>
                        <div class="flex flex-col">
                            @foreach ($g['articles'] as $a)
                                <a href="{{ route('cabinet.admin.help-article', ['article' => $a['id']]) }}" wire:key="art-{{ $a['id'] }}"
                                   draggable="{{ $searching ? 'false' : 'true' }}"
                                   x-data="{ side: null }"
                                   x-on:dragstart="drag = { type: 'a', id: {{ $a['id'] }} }"
                                   x-on:dragend="drag = null"
                                   x-on:dragover="if (@js($searching) || ! drag || drag.type !== 'a' || drag.id === {{ $a['id'] }}) { side = null; return }
                                       $event.preventDefault();
                                       const r = $el.getBoundingClientRect(); side = ($event.clientY - r.top) / r.height < 0.5 ? 'before' : 'after'"
                                   x-on:dragleave="side = null"
                                   x-on:drop="if (drag && side) { $event.preventDefault(); $event.stopPropagation();
                                       $wire.moveArticle(drag.id, {{ $a['id'] }}, side === 'before'); side = null; drag = null }"
                                   class="group relative flex items-center gap-3 border-t border-line py-3 text-ink last:pb-0">
                                    <span x-show="side === 'before'" x-cloak class="pointer-events-none absolute inset-x-0 top-0 border-t-2 border-ink"></span>
                                    <span x-show="side === 'after'" x-cloak class="pointer-events-none absolute inset-x-0 bottom-0 border-b-2 border-ink"></span>
                                    @unless ($searching)<x-ui.icon name="grip" size="s" class="cursor-grab text-faint" />@endunless
                                    <span class="min-w-0 flex-1 truncate text-t1-s font-medium group-hover:underline group-hover:decoration-line-strong group-hover:underline-offset-4">{{ $a['title'] }}</span>
                                    @if ($a['video'])<span class="hidden shrink-0 text-t2 text-muted lg:inline">с видео</span>@endif
                                    @if ($a['draft'])
                                        <x-ui.badge>Черновик</x-ui.badge>
                                    @else
                                        <span class="hidden shrink-0 text-t2 text-muted lg:inline">{{ $a['views'] }}</span>
                                    @endif
                                    <x-ui.icon name="chevron-right" class="text-faint group-hover:text-ink" />
                                </a>
                            @endforeach
                            @if ($g['articles'] === [])
                                <p class="border-t border-line pt-3 text-t2 text-muted">Пока пусто — создайте статью и выберите эту категорию</p>
                            @endif
                        </div>
                    </div>
                @empty
                    @if ($searching)
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <span class="text-t2 text-muted">Ничего не нашлось — проверьте написание или поищите в другом разделе</span>
                            <x-ui.btn size="s" wire:click="clearSearch" class="self-start lg:self-auto">Сбросить поиск</x-ui.btn>
                        </div>
                    @else
                        <p class="text-t2 text-muted">Пока пусто — создайте первую категорию этого раздела</p>
                    @endif
                @endforelse

                @if (! $searching && $groups->isNotEmpty())
                    <p class="mt-6 border-t border-line pt-4 text-t2 text-muted">Перетащите статью или категорию, чтобы поменять порядок — на сайте он такой же</p>
                @endif
            </x-ui.card>

            {{-- Черновики — фокус-блок --}}
            <x-ui.card focus aria-labelledby="h-drafts">
                <x-ui.card-head id="h-drafts" title="Черновики">
                    @if ($drafts->isNotEmpty())
                        <x-slot:action><span class="text-t1 font-medium">{{ $drafts->count() }}</span></x-slot:action>
                    @endif
                </x-ui.card-head>
                @if ($drafts->isEmpty())
                    <span class="text-t2 text-muted">Черновиков нет — все статьи на сайте</span>
                @else
                    <x-ui.list>
                        @foreach ($drafts as $d)
                            <div wire:key="draft-{{ $d['id'] }}" class="flex flex-col gap-3 border-t border-line py-4 last:pb-0">
                                <div class="flex min-w-0 flex-col gap-1">
                                    <a href="{{ route('cabinet.admin.help-article', ['article' => $d['id']]) }}" class="text-t1-s font-medium hover:underline hover:underline-offset-4">{{ $d['title'] }}</a>
                                    <span class="text-t2 text-muted">{{ $d['meta'] }}</span>
                                </div>
                                <x-ui.btn size="s" wire:click="publish({{ $d['id'] }})" class="self-start">Опубликовать</x-ui.btn>
                            </div>
                        @endforeach
                    </x-ui.list>
                @endif
            </x-ui.card>
        </div>
    </div>

    {{-- Новая категория / изменить категорию --}}
    @if ($catOpen)
        <x-ui.modal :title="$catId ? 'Изменить категорию' : 'Новая категория'" :sub="$catId ? ($catCount ? plural_ru($catCount, 'статья', 'статьи', 'статей') : 'Пока без статей') : null" close="closeCategory">
            <div class="flex flex-col gap-2">
                <span id="cf-aud" class="text-t2 font-medium">Раздел</span>
                <x-ui.seg :items="$audiences" model="catAudience" :active="$catAudience" aria-labelledby="cf-aud" />
            </div>
            <x-ui.field label="Название" name="catName" wire:model="catName" placeholder="Например, «Оплата»" />
            <x-ui.field label="Описание" name="catDescription" :rows="2" optional wire:model="catDescription" placeholder="Одна строка под названием на сайте" />
            <div class="flex flex-col gap-2">
                <span id="cf-icon" class="text-t2 font-medium">Иконка</span>
                <div class="flex flex-wrap gap-2" role="radiogroup" aria-labelledby="cf-icon">
                    @foreach ($icons as $key => $label)
                        <x-ui.chip square :on="$catIcon === $key" role="radio" aria-checked="{{ $catIcon === $key ? 'true' : 'false' }}" aria-label="{{ $label }}" title="{{ $label }}" wire:click="pickIcon('{{ $key }}')"><x-ui.icon :name="$key" /></x-ui.chip>
                    @endforeach
                </div>
            </div>
            <div class="flex items-center justify-between gap-4">
                <div class="flex min-w-0 flex-col gap-1">
                    <span class="text-t1-s font-medium">Показывать на сайте</span>
                    @unless ($catShow)<span class="text-t2 text-muted">Категория и её статьи не видны, пока вы их наполняете</span>@endunless
                </div>
                <x-ui.switch :checked="$catShow" label="Показывать на сайте" wire:click="$toggle('catShow')" />
            </div>
            <x-slot:footer>
                <x-ui.btn wire:click="closeCategory">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveCategory" wire:loading.attr="disabled" wire:target="saveCategory">{{ $catId ? 'Сохранить' : 'Создать категорию' }}</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Удаление категории --}}
    @if ($deleting)
        <x-ui.modal title="Удалить категорию?" :sub="$deleting->name . ' · ' . mb_strtolower($audiences[$deleting->audience] ?? '')" close="closeDelete" width="s">
            <p class="text-t1-s">
                @if ($deleting->articles_count)
                    Вместе с категорией удалятся {{ plural_ru($deleting->articles_count, 'статья', 'статьи', 'статей') }}. Вернуть их нельзя.
                @else
                    В категории нет статей. С сайта она пропадёт сразу.
                @endif
            </p>
            @if ($deleting->articles_count && $deleting->is_published)
                <span class="text-t2 text-muted">Статьи ещё пригодятся? <button type="button" class="link" wire:click="hideInstead">Скройте категорию с сайта</button></span>
            @endif
            <x-slot:footer>
                <x-ui.btn wire:click="closeDelete">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="deleteCategory">{{ $deleting->articles_count ? 'Удалить со статьями' : 'Удалить категорию' }}</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
