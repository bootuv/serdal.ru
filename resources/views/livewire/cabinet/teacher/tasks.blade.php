<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Задания">
        <x-slot:actions>
            <x-ui.btn variant="dark" icon="plus" :href="$newUrl">Выдать задание</x-ui.btn>
        </x-slot:actions>
    </x-ui.page-head>

    <div class="flex flex-col gap-6">
        <x-ui.tabs model="tab" :active="$tab" :items="['review' => 'Нужно проверить', 'issued' => 'Выданные', 'all' => 'Все']"
                   :counts="['review' => $reviewCount]" aria-label="Какие задания показать" />

        @if ($tab === 'review')
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                {{-- Фокус-блок: работы на проверку, самые давние — сверху --}}
                <x-ui.card focus class="min-w-0 lg:col-span-2" aria-labelledby="to-review">
                    <x-ui.card-head id="to-review" title="Нужно проверить">
                        @if ($review->isNotEmpty())<x-slot:action><span class="text-t2 text-muted">Сначала давние</span></x-slot:action>
                @endif
                    </x-ui.card-head>
                    @if ($review->isEmpty())
                        <p class="text-t2 text-muted">Пока пусто — сданные работы учеников появятся здесь.</p>
                    @else
                        <x-ui.list>
                            @foreach ($review as $r)
                                @if ($loop->first)
                                    {{-- Телефон: бейдж под подписью, кнопка под текстом на всю ширину --}}
                                    <x-ui.row align="start" class="-mx-4 flex-col rounded-lg border-t-0 bg-white px-4 shadow-card last:pb-4 sm:flex-row sm:items-center" wire:key="rv-{{ $r['id'] }}">
                                        <div class="flex w-full min-w-0 flex-1 items-center gap-4">
                                            <x-ui.avatar :name="$r['student']" :id="$r['studentId']" />
                                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                                <a href="{{ $r['url'] }}" class="line-clamp-2 break-words text-t1 font-medium sm:line-clamp-none sm:truncate">{{ $r['title'] }}</a>
                                                <span class="text-t2 text-muted">{{ $r['sub'] }}@if ($r['wait']) · <x-ui.em :danger="$r['overdue']">{{ $r['wait'] }}</x-ui.em>@endif</span>
                                                @if ($r['badge'])<span class="flex sm:hidden"><x-ui.badge :tone="$r['badge'][0]">{{ $r['badge'][1] }}</x-ui.badge></span>@endif
                                            </div>
                                            @if ($r['badge'])<x-ui.badge :tone="$r['badge'][0]" class="hidden sm:inline-flex">{{ $r['badge'][1] }}</x-ui.badge>@endif
                                        </div>
                                        <x-ui.btn variant="primary" :href="$r['url']" class="self-start sm:self-center">Проверить</x-ui.btn>
                                    </x-ui.row>
                                @else
                                    <x-ui.row :href="$r['url']" :class="$loop->index === 1 ? 'border-t-0' : ''" wire:key="rv-{{ $r['id'] }}">
                                        <x-ui.avatar :name="$r['student']" :id="$r['studentId']" />
                                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                                            <span class="line-clamp-2 break-words text-t1 font-medium sm:line-clamp-none sm:truncate">{{ $r['title'] }}</span>
                                            <span class="text-t2 text-muted">{{ $r['sub'] }}</span>
                                            @if ($r['badge'])<span class="flex sm:hidden"><x-ui.badge :tone="$r['badge'][0]">{{ $r['badge'][1] }}</x-ui.badge></span>@endif
                                        </div>
                                        @if ($r['badge'])<x-ui.badge :tone="$r['badge'][0]" class="hidden sm:inline-flex">{{ $r['badge'][1] }}</x-ui.badge>@endif
                                    </x-ui.row>
                                @endif
                            @endforeach
                        </x-ui.list>
                        @if ($reviewMore > 0)
                            <button type="button" wire:click="showMore" class="link self-start text-t2">Показать ещё {{ min($reviewMore, 30) }}</button>
                        @endif
                    @endif
                </x-ui.card>

                {{-- Что ещё не сдали --}}
                <x-ui.card class="min-w-0" aria-labelledby="waiting">
                    <x-ui.card-head id="waiting" title="Ждём от учеников">
                        <x-slot:action><button type="button" wire:click="$set('tab', 'issued')" class="link text-t2">Все выданные</button></x-slot:action>
                    </x-ui.card-head>
                    @if ($waiting->isEmpty())
                        <p class="text-t2 text-muted">Пока пусто — здесь будут задания, которые ученики ещё не сдали.</p>
                    @else
                        <x-ui.list>
                            @foreach ($waiting as $w)
                                <x-ui.row :href="$w['url']" wire:key="wt-{{ $loop->index }}">
                                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                                        <span class="truncate text-t1 font-medium">{{ $w['title'] }}</span>
                                        <span class="text-t2 text-muted">{{ $w['sub'] }}@if ($w['em']) · <x-ui.em>{{ $w['em'] }}</x-ui.em>@endif @if (! empty($w['progress']))· {{ $w['progress'] }}@endif</span>
                                        @if ($w['badge'])<span class="flex"><x-ui.badge :tone="$w['badge'][0]">{{ $w['badge'][1] }}</x-ui.badge></span>@endif
                                    </div>
                                </x-ui.row>
                            @endforeach
                        </x-ui.list>
                    @endif
                </x-ui.card>
            </div>
        @else
            @if ($tab === 'all' && $hasAny)
                {{-- Фильтры: ученик, занятие, поиск по названию --}}
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    @if (count($students) > 1)
                        <div class="w-full sm:w-sidebar">
                            <x-ui.select name="studentId" wire:model.live="studentId" :options="$students" placeholder="Все ученики" aria-label="Ученик" />
                        </div>
                    @endif
                    @if (count($rooms) > 1)
                        <div class="w-full sm:w-sidebar">
                            <x-ui.select name="roomId" wire:model.live="roomId" :options="$rooms" placeholder="Все занятия" aria-label="Занятие" />
                        </div>
                    @endif
                    <div class="sm:ml-auto">
                        <x-ui.search placeholder="Найти задание" wire:model.live.debounce.300ms="search" />
                    </div>
                </div>
            @endif

            @if ($list->isEmpty())
                <x-ui.card>
                    @if (! $hasAny)
                        <x-ui.empty icon="tasks" title="Заданий пока нет" text="Выдайте первое задание — ученики получат уведомление и увидят его в своём кабинете." />
                    @elseif ($tab === 'issued')
                        <p class="text-t2 text-muted">Пока пусто — все выданные задания проверены.</p>
                    @elseif ($filtered)
                        <p class="text-t2 text-muted">Ничего не нашлось — измените название или выберите всех учеников и все занятия.</p>
                    @else
                        <p class="text-t2 text-muted">Пока пусто — выданные задания появятся здесь.</p>
                    @endif
                </x-ui.card>
            @else
                <x-ui.card :aria-label="$tab === 'issued' ? 'Выданные задания' : 'Все задания'">
                    <x-ui.list>
                        @foreach ($list as $it)
                            <x-ui.row :href="$it['url']" class="first:border-transparent first:pt-0" wire:key="task-{{ $it['id'] }}">
                                @if ($it['group'])
                                    <x-ui.avatar group />
                                @else
                                    <x-ui.avatar :name="$it['who']" :id="$it['avatarId']" />
                                @endif
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="line-clamp-2 break-words text-t1 font-medium sm:line-clamp-none sm:truncate">{{ $it['title'] }}</span>
                                    <span class="text-t2 text-muted">{{ $it['who'] }}@if ($it['dueEm']) · <x-ui.em>{{ $it['dueEm'] }}</x-ui.em>@elseif ($it['due']) · {{ $it['due'] }}@endif</span>
                                    {{-- Телефон: прогресс и бейдж под подписью --}}
                                    @if ($it['prog'] || $it['badge'])
                                        <span class="flex flex-wrap items-center gap-2 sm:hidden">
                                            @if ($it['prog'])<span class="text-t2 text-muted">{{ $it['prog'][0] }} <x-ui.em>{{ $it['prog'][1] }}</x-ui.em></span>@endif
                                            @if ($it['badge'])<x-ui.badge :tone="$it['badge'][0]">{{ $it['badge'][1] }}</x-ui.badge>@endif
                                        </span>
                                    @endif
                                </div>
                                @if ($it['prog'])
                                    <span class="hidden whitespace-nowrap text-t2 text-muted sm:inline">{{ $it['prog'][0] }} <x-ui.em>{{ $it['prog'][1] }}</x-ui.em></span>
                                @endif
                                @if ($it['badge'])<x-ui.badge :tone="$it['badge'][0]" class="hidden sm:inline-flex">{{ $it['badge'][1] }}</x-ui.badge>@endif
                            </x-ui.row>
                        @endforeach
                    </x-ui.list>
                    @if ($listMore > 0)
                        <button type="button" wire:click="showMore" class="link self-start text-t2">Показать ещё {{ min($listMore, 30) }}</button>
                    @endif
                </x-ui.card>
            @endif
        @endif
    </div>
</div>
