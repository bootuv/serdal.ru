{{-- Админка · отчёт о проведённом занятии (макет AdminSessions, отчёт). --}}
<div class="grid grid-cols-1 gap-6 lg:gap-8">
    <div class="flex flex-col gap-4">
        <a href="{{ $backUrl }}" class="inline-flex items-center gap-2 self-start text-t1-s font-medium text-muted hover:text-ink"><x-ui.icon name="arrow-left" size="s" />{{ $backLabel }}</a>
        <header class="flex flex-col gap-4">
            <h1 class="text-h1-m font-medium lg:text-h1">{{ $room->name }}</h1>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <p class="text-t1 text-muted">{{ $facts }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($recordingUrl)<x-ui.btn size="l" :href="$recordingUrl">Запись занятия</x-ui.btn>@endif
                    @if ($canDelete)<x-ui.btn size="l" wire:click="askDeleteSession({{ $sessionId }})">Удалить занятие</x-ui.btn>@endif
                </div>
            </div>
        </header>
    </div>

    @if ($request)
        <x-ui.card focus aria-labelledby="sr-req">
            <x-ui.card-head id="sr-req" :title="$request['title']">
                <x-slot:action><span class="text-t2 text-muted">{{ $request['sent'] }}</span></x-slot:action>
            </x-ui.card-head>
            <p class="text-t1">{{ $request['reason'] ?: 'Учитель не указал причину' }}</p>
            <div class="flex flex-wrap gap-2">
                <x-ui.btn wire:click="askRejectSession({{ $sessionId }})">Отклонить</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="askDeleteSession({{ $sessionId }})">Удалить занятие</x-ui.btn>
            </div>
        </x-ui.card>
    @endif

    <x-ui.card :focus="! $request" aria-labelledby="sr-sum">
        <x-ui.card-head id="sr-sum" :title="$live ? 'Пока идёт' : 'Итог занятия'" />
        <div class="grid grid-cols-2 gap-6 lg:grid-cols-6">
            @foreach ($stats as $stat)
                <div class="flex min-w-0 flex-col gap-1">
                    <span class="whitespace-nowrap text-num font-medium">{{ $stat['value'] }}</span>
                    <span class="text-t2 text-muted">{{ $stat['label'] }}</span>
                </div>
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.card class="gap-0" aria-labelledby="sr-ppl">
        <x-ui.card-head id="sr-ppl" title="Ученики" class="mb-4" />
        @if ($students->isEmpty())
            <p class="text-t2 text-muted">В занятии не было учеников.</p>
        @else
            <div class="hidden gap-4 pb-3 text-t3 font-medium text-muted lg:grid lg:grid-cols-12">
                <span class="col-span-5">Ученик</span><span class="text-right">В классе</span><span class="text-right">Микрофон</span><span class="text-right">Камера</span><span class="text-right">Сообщения</span><span class="text-right">Реакции</span><span class="text-right">Рука</span><span class="text-right">Активность</span>
            </div>
            <div class="flex flex-col">
                @foreach ($students as $p)
                    <div class="flex flex-col gap-2 border-t border-line py-4 last:pb-0 lg:grid lg:grid-cols-12 lg:items-center lg:gap-4" wire:key="sr-p-{{ $p['id'] }}">
                        <div class="flex min-w-0 items-center gap-3 lg:col-span-5">
                            <x-ui.avatar :name="$p['name']" :id="$p['id']" :photo="$p['photo']" :class="$p['came'] ? '' : 'opacity-60'" />
                            <span class="truncate text-t1-s font-medium">{{ $p['name'] }}</span>
                        </div>
                        @if ($p['came'])
                            <span class="text-t2 text-muted lg:hidden">{{ $p['inRoom'] === '—' ? 'На занятии' : 'В классе ' . $p['inRoom'] . ' · микрофон ' . $p['mic'] . ' · активность ' . $p['score'] }}</span>
                            @foreach (['inRoom', 'mic', 'cam', 'msg', 'react', 'hand'] as $k)
                                <span @class(['hidden text-right text-t1-s lg:block', 'text-muted' => in_array($p[$k], ['—', '0'], true)])>{{ $p[$k] }}</span>
                            @endforeach
                            <span class="hidden text-right text-t1-s font-semibold lg:block">{{ $p['score'] }}</span>
                        @else
                            <span class="text-t2 font-semibold text-ink lg:text-right lg:text-t1-s">пропуск</span>
                            @for ($i = 0; $i < 6; $i++)<span class="hidden text-right text-t1-s text-muted lg:block">—</span>@endfor
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
        @if ($teacherNote)<p class="mt-4 text-t2 text-muted">{{ $teacherNote }}</p>@endif
    </x-ui.card>

    <x-ui.card aria-labelledby="sr-feed">
        <x-ui.card-head id="sr-feed" title="Ход занятия" />
        <div class="flex flex-col">
            @foreach ($feed as $f)
                <div class="flex gap-4 border-t border-line py-3 text-t1-s first:border-t-0 first:pt-0 last:pb-0">
                    <span class="w-12 shrink-0 font-medium tabular-nums">{{ $f['time'] }}</span>
                    <span class="min-w-0 flex-1">{{ $f['text'] }}</span>
                </div>
            @endforeach
        </div>
    </x-ui.card>

    @include('livewire.cabinet.admin.partials.lessons-decision-modals')
</div>
