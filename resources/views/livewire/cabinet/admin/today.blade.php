{{-- «Сегодня» администратора. Макет: AdminToday. Очередь исключений (фокус), идущие занятия, итоги периода с графиком. --}}
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Сегодня" :sub="$today" />

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        {{-- Нужно разобрать --}}
        <x-ui.card focus class="min-w-0 lg:col-span-2" aria-labelledby="t-queue">
            <x-ui.card-head id="t-queue" :title="$queue ? 'Нужно разобрать' : 'Всё разобрано'" />
            @if ($queue)
                <x-ui.list>
                    @foreach ($queue as $q)
                        <x-ui.row :href="$q['href']" wire:key="q-{{ $loop->index }}">
                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="text-t1 font-medium">{{ $q['title'] }}</span>
                                <span class="truncate text-t2 text-muted">{{ $q['who'] }}@if ($q['em']) · <x-ui.em>{{ $q['em'] }}</x-ui.em>@endif</span>
                            </div>
                            <span class="min-w-6 text-right text-t1 font-semibold tabular-nums">{{ $q['n'] }}</span>
                        </x-ui.row>
                    @endforeach
                </x-ui.list>
            @else
                <div class="flex items-center gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded bg-white text-ok-fg"><x-ui.icon name="check" /></span>
                    <span class="text-t2 text-ink">Новых заявок, жалоб, обращений и зависших платежей нет. Новое появится здесь.</span>
                </div>
            @endif
        </x-ui.card>

        {{-- Идут сейчас --}}
        <x-ui.card class="min-w-0" aria-labelledby="t-live">
            <x-ui.card-head id="t-live" :title="$live->isEmpty() ? 'Сейчас занятий нет' : 'Идут сейчас: ' . plural_ru($live->count(), 'занятие', 'занятия', 'занятий')" />
            @if ($live->isNotEmpty())
                <x-ui.list>
                    @foreach ($live as $l)
                        <div class="flex flex-col gap-3 border-t border-line py-4 last:pb-0" wire:key="live-{{ $l['id'] }}">
                            <div class="flex min-w-0 flex-col gap-1">
                                <span class="text-t1 font-medium">{{ $l['title'] }}</span>
                                <span class="text-t2 text-muted">{{ $l['who'] }} ·
                                    @if ($l['over'])<x-ui.em>{{ $l['duration'] }}</x-ui.em>, план — {{ $l['plan'] }}@else{{ $l['duration'] }}@endif
                                </span>
                            </div>
                            <x-ui.btn size="s" icon="logout" :href="$l['joinUrl']" target="_blank" rel="noopener" class="self-start">Подключиться</x-ui.btn>
                        </div>
                    @endforeach
                </x-ui.list>
            @endif
            @if ($lessonsUrl)
                <a href="{{ $lessonsUrl }}" class="link self-start text-t1-s">Все занятия</a>
            @endif
        </x-ui.card>
    </div>

    {{-- Итоги периода --}}
    <x-ui.card class="gap-6" aria-labelledby="t-stats">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <h2 id="t-stats" class="text-h2 font-medium">{{ $summary['title'] }}</h2>
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
                <label class="relative flex">
                    <span class="sr-only">Учитель</span>
                    <select wire:model.live="teacher" class="h-9 w-full appearance-none truncate rounded bg-white pl-3 pr-8 text-t2 text-ink shadow-outline outline-none focus:shadow-outline-ink lg:w-sidebar">
                        <option value="">Все учителя</option>
                        @foreach ($teachers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    <x-ui.icon name="chevron-down" size="s" class="pointer-events-none absolute right-3 top-3 text-muted" />
                </label>
                <x-ui.seg fit model="period" :active="$period" :items="['7' => '7 дней', '30' => '30 дней', '90' => '90 дней']" />
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 border-b border-line pb-6 sm:grid-cols-3">
            @foreach ($summary['stats'] as $s)
                <div class="flex min-w-0 flex-col gap-1">
                    <span class="whitespace-nowrap text-num font-medium tabular-nums">{{ $s['v'] }}</span>
                    <span class="text-t2 text-muted">{{ $s['label'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="flex flex-col gap-4">
            <span class="text-t1 font-medium">Занятия по дням</span>
            @include('livewire.cabinet.admin.partials.day-chart', ['chart' => $summary['chart']])
        </div>
    </x-ui.card>
</div>
