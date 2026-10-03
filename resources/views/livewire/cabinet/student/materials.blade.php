<div class="flex flex-col gap-6 lg:gap-8">
    @if ($openFolder)
        {{-- Открытая папка --}}
        <x-ui.page-head :title="$openFolder['name']" :sub="$multi ? $openFolder['teacher'] : null"
                        :back="$openFolder['back']" :back-label="$openFolder['backLabel']" />

        <x-ui.card aria-label="{{ $openFolder['name'] }}">
            @if ($openFolder['rows']->isEmpty())
                <p class="text-t2 text-muted">Пока пусто — учитель ещё ничего не положил в эту папку.</p>
            @else
                <x-ui.list>
                    @foreach ($openFolder['rows'] as $item)
                        @include('livewire.cabinet.student.partials.material-row', ['item' => $item])
                    @endforeach
                </x-ui.list>
            @endif
            @if ($hasMore)
                <x-ui.btn size="s" class="self-start" wire:click="showMore">Показать ещё</x-ui.btn>
            @endif
        </x-ui.card>
    @else
        <x-ui.page-head title="Материалы"
                        :sub="$total ? plural_ru($total, 'файл', 'файла', 'файлов') . ' · ' . $teachers->pluck('name')->join(', ') : null" />

        @if ($teachers->isEmpty())
            <x-ui.empty icon="folder" title="Материалов пока нет" text="Здесь появятся файлы, которыми поделятся ваши учителя." />
        @else
            <div @class(['flex flex-col gap-4 lg:flex-row lg:items-center', 'lg:justify-between' => $multi, 'lg:justify-end' => ! $multi])>
                @if ($multi)
                    <x-ui.seg model="teacher" :active="$teacher" aria-label="Учитель" fit class="min-w-0"
                              :items="['all' => 'Все материалы'] + $teachers->pluck('name', 'id')->all()" />
                @endif
                <div class="lg:shrink-0">
                    <x-ui.search placeholder="Поиск по материалам" wire:model.live.debounce.400ms="search" />
                </div>
            </div>

            @if ($searching)
                {{-- Результаты поиска --}}
                <x-ui.card aria-labelledby="m-found">
                    <x-ui.card-head id="m-found" title="Найдено" />
                    @if ($results->isEmpty())
                        <p class="text-t2 text-muted">Ничего не нашлось — попробуйте другое слово.</p>
                    @else
                        <x-ui.list>
                            @foreach ($results as $item)
                                @include('livewire.cabinet.student.partials.material-row', ['item' => $item])
                            @endforeach
                        </x-ui.list>
                    @endif
                    @if ($hasMore)
                        <x-ui.btn size="s" class="self-start" wire:click="showMore">Показать ещё</x-ui.btn>
                    @endif
                </x-ui.card>
            @else
                {{-- Фокус: новое за неделю --}}
                @if ($fresh->isNotEmpty())
                    <x-ui.card focus aria-labelledby="m-new">
                        <x-ui.card-head id="m-new" title="Новое за неделю" />
                        <x-ui.list>
                            @foreach ($fresh as $item)
                                <x-ui.row :href="$item['href']" target="_blank" rel="noopener">
                                    <x-ui.file-tile :name="$item['file']" :thumb="$item['thumb']" onMint />
                                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                                        <span class="truncate text-t1 font-medium">{{ $item['title'] }}</span>
                                        <span class="text-t2 text-muted">{{ $item['lead'] }}@if ($item['lead'] && $item['em']) · @endif @if ($item['em'])<x-ui.em>{{ $item['em'] }}</x-ui.em>@endif</span>
                                    </div>
                                </x-ui.row>
                            @endforeach
                        </x-ui.list>
                    </x-ui.card>
                @endif

                {{-- По учителям --}}
                <div @class(['grid grid-cols-1 items-start gap-6', 'lg:grid-cols-2' => $sections->count() > 1])>
                    @foreach ($sections as $section)
                        <x-ui.card wire:key="t-{{ $section['id'] }}" aria-label="{{ $section['name'] }}">
                            <div class="flex min-h-9 items-center gap-3">
                                <x-ui.avatar :name="$section['name']" :id="$section['id']" :photo="$section['photo']" />
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <h2 class="truncate text-h2 font-medium">{{ $section['name'] }}</h2>
                                    @if ($section['subjects'])<span class="truncate text-t2 text-muted">{{ $section['subjects'] }}</span>@endif
                                </div>
                                @if ($section['more'] && $sections->count() > 1)
                                    <button type="button" class="link shrink-0 text-t2" wire:click="$set('teacher', '{{ $section['id'] }}')">Все</button>
                                @endif
                            </div>
                            @if ($section['rows']->isEmpty())
                                <p class="text-t2 text-muted">Пока пусто — учитель ещё ничего не открыл.</p>
                            @else
                                <x-ui.list>
                                    @foreach ($section['rows'] as $item)
                                        @include('livewire.cabinet.student.partials.material-row', ['item' => $item])
                                    @endforeach
                                </x-ui.list>
                            @endif
                            @if ($hasMore && $sections->count() === 1)
                                <x-ui.btn size="s" class="self-start" wire:click="showMore">Показать ещё</x-ui.btn>
                            @endif
                        </x-ui.card>
                    @endforeach
                </div>
            @endif
        @endif
    @endif
</div>
