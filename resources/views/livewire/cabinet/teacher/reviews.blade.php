{{-- Отзывы учеников. Макеты: TeacherReviews, RvTeacherOpen (окно отзыва), RvShareDesktop (поделиться), RvReport (жалоба), RvTeacherEmpty. --}}
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Отзывы" :sub="$pageLabel ? 'Видны на вашей странице ' . $pageLabel : null">
        @if ($page)
            <x-slot:actions>
                <x-ui.btn :href="$page" target="_blank" rel="noopener" class="hidden lg:inline-flex">Открыть мою страницу</x-ui.btn>
                <x-ui.btn :href="$page" target="_blank" rel="noopener" square icon="user" class="lg:hidden" aria-label="Открыть мою страницу" />
            </x-slot:actions>
        @endif
    </x-ui.page-head>

    @if ($summary['count'] === 0 && $fresh->isEmpty() && $all->isEmpty())
        <x-ui.card focus>
            <x-ui.empty icon="star" title="Пока нет отзывов" text="Ученики оставляют отзыв после первого занятия. Он появится здесь и на вашей странице.">
                <x-slot:action>
                    <x-ui.btn variant="primary" :href="url('/tutor/messenger')">Попросить учеников об отзыве</x-ui.btn>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
            <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
                {{-- Фокус: новые (непрочитанные) --}}
                @if ($fresh->isNotEmpty())
                    <x-ui.card focus aria-labelledby="r-new">
                        <x-ui.card-head id="r-new" title="Новые" />
                        <x-ui.list>
                            @foreach ($fresh as $r)
                                <x-ui.row align="start" wire:key="new-{{ $r['id'] }}">
                                    <x-ui.avatar :user="$r['user']" :name="$r['name']" />
                                    <div class="flex min-w-0 flex-1 flex-col gap-3">
                                        @include('livewire.cabinet.teacher.partials.review-head', ['r' => $r, 'new' => true])
                                        <p class="line-clamp-3 whitespace-pre-line text-t1">{{ $r['text'] }}</p>
                                        <div class="flex flex-wrap items-center gap-4">
                                            <button type="button" class="link text-t2" wire:click="read({{ $r['id'] }})">Читать полностью</button>
                                            <x-ui.btn size="s" icon="share" wire:click="share({{ $r['id'] }})">Поделиться</x-ui.btn>
                                            @if ($r['reported'])
                                                <x-ui.badge>Жалоба на проверке</x-ui.badge>
                                                <span class="text-t2 text-muted">Отзыв виден, пока модератор проверяет.</span>
                                            @else
                                                <button type="button" class="text-t2 font-medium text-muted underline decoration-line-strong underline-offset-4 hover:text-ink" wire:click="askReport({{ $r['id'] }})">Пожаловаться</button>
                                            @endif
                                        </div>
                                    </div>
                                </x-ui.row>
                            @endforeach
                        </x-ui.list>
                    </x-ui.card>
                @endif

                {{-- Все отзывы --}}
                @if ($all->isNotEmpty())
                    <x-ui.card aria-labelledby="r-all">
                        <x-ui.card-head id="r-all" title="Все отзывы">
                            <x-slot:action><span class="text-t2 text-muted">Сначала новые</span></x-slot:action>
                        </x-ui.card-head>
                        <x-ui.list>
                            @foreach ($all as $r)
                                <x-ui.row align="start" wire:key="all-{{ $r['id'] }}">
                                    <x-ui.avatar :user="$r['user']" :name="$r['name']" />
                                    <button type="button" wire:click="read({{ $r['id'] }})" class="group flex min-w-0 flex-1 flex-col gap-3 text-left">
                                        @include('livewire.cabinet.teacher.partials.review-head', ['r' => $r, 'new' => false])
                                        <span class="line-clamp-2 text-t1">{{ $r['text'] }}</span>
                                        <span class="text-t2 font-medium underline decoration-line-strong underline-offset-4 group-hover:decoration-ink">Читать полностью</span>
                                    </button>
                                    <x-ui.btn size="s" square icon="share" wire:click="share({{ $r['id'] }})" aria-label="Поделиться отзывом" title="Поделиться" />
                                </x-ui.row>
                            @endforeach
                        </x-ui.list>
                        @if ($more > 0)
                            <button type="button" class="link self-start text-t2" wire:click="showMore">Показать ещё {{ min($more, 20) }}</button>
                        @endif
                    </x-ui.card>
                @endif
            </div>

            {{-- Ваша оценка --}}
            @if ($summary['count'] > 0)
                <x-ui.card aria-labelledby="r-sum" class="min-w-0">
                    <x-ui.card-head id="r-sum" title="Ваша оценка" />
                    <div class="flex items-center gap-4">
                        <span class="text-num font-medium tabular-nums">{{ str_replace('.', ',', number_format($summary['avg'], 1)) }}</span>
                        <div class="flex flex-col gap-1">
                            <x-ui.stars :value="(int) round($summary['avg'])" />
                            <span class="text-t2 text-muted">{{ plural_ru($summary['count'], 'отзыв', 'отзыва', 'отзывов') }}</span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2" role="list" aria-label="Распределение оценок">
                        @foreach ($summary['bars'] as $stars => $n)
                            @php $pct = $summary['count'] ? round($n / $summary['count'] * 100) : 0; @endphp
                            <div class="flex items-center gap-3 text-t2 text-muted" role="listitem" aria-label="{{ $stars }} из 5: {{ plural_ru($n, 'отзыв', 'отзыва', 'отзывов') }}">
                                <span class="w-4 shrink-0 tabular-nums">{{ $stars }}</span>
                                <svg class="h-2 min-w-0 flex-1" aria-hidden="true">
                                    <rect width="100%" height="8" rx="4" class="fill-soft" />
                                    @if ($pct > 0)<rect width="{{ $pct }}%" height="8" rx="4" class="fill-ink" />@endif
                                </svg>
                                <span class="w-6 shrink-0 text-right tabular-nums">{{ $n }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif
        </div>
    @endif

    {{-- Окно: отзыв целиком --}}
    @if ($opened)
        <x-ui.modal :title="$opened['name']" :sub="$opened['meta']" width="m" close="$set('openId', null)">
            <div class="flex items-center gap-3">
                <x-ui.stars :value="$opened['rating']" />
                <span class="text-t2 text-muted">{{ $opened['at'] }}</span>
            </div>
            <p class="whitespace-pre-line text-t1">{{ $opened['text'] }}</p>
            <x-slot:note>
                @if ($opened['reported'])
                    <x-ui.badge>Жалоба на проверке</x-ui.badge>
                @else
                    <button type="button" class="link text-t2" wire:click="askReport({{ $opened['id'] }})">Пожаловаться</button>
                @endif
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="$set('openId', null)" class="hidden lg:inline-flex">Закрыть</x-ui.btn>
                <x-ui.btn variant="primary" icon="share" wire:click="share({{ $opened['id'] }})">Поделиться</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Окно: поделиться отзывом (картинка для сторис) --}}
    @if ($shared)
        <x-ui.modal title="Поделиться отзывом" :sub="$shared['name'] . ' · ' . $shared['date']" width="l" close="$set('shareId', null)">
            <div class="flex flex-col gap-8 lg:flex-row" x-data="{ copied: false }">
                <img src="{{ $shared['shareUrl'] }}" alt="Картинка с отзывом для сторис" loading="lazy"
                     class="h-auto w-full rounded-lg bg-soft shadow-outline lg:w-sidebar lg:shrink-0">
                <div class="flex min-w-0 flex-1 flex-col gap-6">
                    <div class="flex flex-col gap-1">
                        <span class="text-t1 font-medium">Картинка для сторис</span>
                        <span class="text-t2 text-muted">1080 × 1920 · Telegram, ВКонтакте, WhatsApp</span>
                    </div>
                    <div class="flex flex-col gap-2">
                        <span class="text-t2 font-medium">Подпись к публикации</span>
                        <div class="flex flex-col gap-2 rounded-lg bg-soft p-4" x-ref="caption">
                            <span class="whitespace-pre-line text-t1-s">«{{ $shared['text'] }}»</span>
                            <span class="text-t2 text-muted">— {{ $shared['name'] }}.@if ($pageLabel) Все отзывы: {{ $pageLabel }}@endif</span>
                        </div>
                        <div class="flex">
                            <x-ui.btn size="s" x-show="! copied" x-on:click="navigator.clipboard.writeText($refs.caption.innerText.trim()).then(() => copied = true)">Скопировать текст</x-ui.btn>
                            <x-ui.badge tone="ok" x-show="copied" x-cloak>Текст скопирован</x-ui.badge>
                        </div>
                    </div>
                </div>
            </div>
            <x-slot:footer>
                <x-ui.btn wire:click="$set('shareId', null)">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" icon="download"
                          x-on:click="window.serdalShareReviewCard(@js($shared['shareUrl'])).then(r => { if (r === 'download') $dispatch('toast', { message: 'Картинка сохранена в «Загрузки»' }) }); $wire.set('shareId', null)">Скачать картинку</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Окно: жалоба на отзыв --}}
    @if ($reported)
        <x-ui.modal title="Пожаловаться на отзыв" :sub="$reported['name'] . ' · ' . $reported['at']" width="s" close="$set('reportId', null)">
            <div class="flex flex-col gap-2 rounded-lg bg-soft p-4">
                <x-ui.stars :value="$reported['rating']" />
                <p class="line-clamp-3 text-t1-s">{{ $reported['text'] }}</p>
            </div>
            <p class="text-t2 text-muted">Модератор проверит отзыв. {{ $reported['name'] }} не узнает о жалобе, а отзыв останется на странице до решения.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="$set('reportId', null)">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="sendReport">Отправить жалобу</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
