{{-- Статистика блога (админ — весь блог, учитель — свои статьи): период, просмотры по дням, лайки и комментарии,
     источники, самые читаемые статьи; нажатие на статью — ее статистика (?post=). --}}
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head :title="$teacher ? 'Статистика статей' : 'Статистика блога'" :sub="$selected ? $selected->title : $period" :back="$backUrl" :back-label="$teacher ? 'Мои статьи' : 'Блог'">
        @if ($selected)
            <x-slot:actions>
                <a href="{{ $selected->url }}" target="_blank" rel="noopener" class="link mr-4 hidden text-t1-s lg:inline">Открыть на сайте</a>
                <x-ui.btn wire:click="showPost(null)">Все статьи</x-ui.btn>
            </x-slot:actions>
        @endif
    </x-ui.page-head>

    <x-ui.seg fit :items="$periods" model="days" :active="(string) $days" aria-label="Период" />

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        <div class="flex min-w-0 flex-1 flex-col gap-6">
            <x-ui.card aria-labelledby="bs-views">
                <x-ui.card-head id="bs-views" title="Просмотры">
                    <x-slot:action><span class="text-h2 font-medium">{{ number_format($report['views'], 0, ',', ' ') }}</span></x-slot:action>
                </x-ui.card-head>
                <x-ui.bars :items="$report['days']" :label="'Просмотры по дням ' . mb_strtolower($period)" :caption="$change ?? $period" />
                @if ($report['since'])
                    <span class="text-t3 text-muted">Просмотры по дням считаем с {{ $report['since'] }}. Роботов поисковиков, админов и самого автора не считаем</span>
                @endif
            </x-ui.card>

            @unless ($selected)
                <x-ui.card class="gap-0" aria-labelledby="bs-posts">
                    <x-ui.card-head id="bs-posts" title="{{ $teacher ? 'Мои статьи' : 'Статьи' }}" class="pb-4" />
                    @forelse ($report['posts'] as $item)
                        <x-ui.row wire:key="bs-{{ $item['id'] }}">
                            <button type="button" wire:click="showPost({{ $item['id'] }})" class="flex min-w-0 flex-1 flex-col gap-1 text-left">
                                <span class="truncate text-t1 font-medium">{{ $item['title'] }}</span>
                                <span class="text-t2 text-muted">
                                    @unless ($teacher){{ $item['author'] }} · @endunless{{ plural_ru($item['total'], 'просмотр', 'просмотра', 'просмотров') }} всего · {{ plural_ru($item['likes'], 'лайк', 'лайка', 'лайков') }} · {{ plural_ru($item['comments'], 'комментарий', 'комментария', 'комментариев') }}
                                </span>
                            </button>
                            <span class="shrink-0 text-t1-s font-medium" title="Просмотры {{ mb_strtolower($period) }}">{{ number_format($item['views'], 0, ',', ' ') }}</span>
                        </x-ui.row>
                    @empty
                        <p class="text-t2 text-muted">{{ $teacher ? 'Здесь появятся ваши опубликованные статьи.' : 'Опубликованных статей пока нет.' }}</p>
                    @endforelse
                </x-ui.card>
            @endunless
        </div>

        <div class="flex w-full flex-col gap-6 lg:w-sidebar lg:shrink-0">
            <x-ui.card aria-labelledby="bs-reactions">
                <x-ui.card-head id="bs-reactions" title="Реакции" />
                <dl class="flex flex-col gap-3">
                    @foreach (array_filter(['Лайки' => $report['likes'], 'Комментарии' => $report['comments'], 'Новые подписчики' => $report['followers']], fn ($v) => $v !== null) as $name => $value)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-t2 text-muted">{{ $name }}</dt>
                            <dd class="text-t1-s font-medium">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-ui.card>

            <x-ui.card aria-labelledby="bs-sources">
                <x-ui.card-head id="bs-sources" title="Откуда приходят" />
                @forelse ($report['sources'] as $source)
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-t2">{{ $source['label'] }}</span>
                            <span class="text-t2 text-muted">{{ $source['value'] }} · {{ $sourcesTotal ? round($source['value'] / $sourcesTotal * 100) : 0 }}%</span>
                        </div>
                        <x-ui.progress :value="$source['value']" :max="$sourcesTotal" :label="$source['label']" />
                    </div>
                @empty
                    <p class="text-t2 text-muted">Появится, когда статьи начнут читать.</p>
                @endforelse
            </x-ui.card>

            @if ($report['authors']->isNotEmpty())
                <x-ui.card class="gap-0" aria-labelledby="bs-authors">
                    <x-ui.card-head id="bs-authors" title="Авторы" class="pb-4" />
                    @foreach ($report['authors'] as $author)
                        <x-ui.row>
                            <x-ui.text :title="$author['name']" :sub="plural_ru($author['posts'], 'статья', 'статьи', 'статей')" />
                            <span class="shrink-0 text-t1-s font-medium">{{ number_format($author['views'], 0, ',', ' ') }}</span>
                        </x-ui.row>
                    @endforeach
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
