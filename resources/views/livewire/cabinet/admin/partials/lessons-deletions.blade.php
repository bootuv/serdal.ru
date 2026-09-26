{{-- Админка · Занятия · Запросы на удаление (макет AdminSessions, «Запросы на удаление»). --}}
@if ($request)
    <x-ui.card focus aria-labelledby="rq-title" wire:key="rq-{{ $request['id'] }}">
        <x-ui.card-head id="rq-title" :title="$request['title']">
            <x-slot:action><a href="{{ $request['url'] }}" class="link text-t2">Отчёт о занятии</a></x-slot:action>
        </x-ui.card-head>
        <p class="text-t2 text-muted">
            {{ collect([$request['teacher'], $request['range']])->filter()->implode(' · ') }} · <x-ui.em>{{ $request['duration'] }}</x-ui.em>{{ $request['came'] ? ' · ' . $request['came'] : '' }}
        </p>
        <div class="flex flex-col gap-2">
            <span class="text-t2 font-medium">Причина</span>
            <p class="text-t1">{{ $request['reason'] ?: 'Учитель не указал причину' }}</p>
            <span class="text-t2 text-muted">{{ $request['sent'] }}</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-ui.btn wire:click="askRejectSession({{ $request['id'] }})">Отклонить</x-ui.btn>
            <x-ui.btn variant="dark" wire:click="askDeleteSession({{ $request['id'] }})">Удалить занятие</x-ui.btn>
        </div>
    </x-ui.card>
@else
    <x-ui.card aria-label="Запросы на удаление">
        <p class="text-t2 text-muted">Новых запросов нет. Когда учитель попросит удалить занятие, запрос появится здесь и в меню.</p>
    </x-ui.card>
@endif

@if ($others->isNotEmpty())
    <x-ui.card aria-labelledby="rq-more">
        <x-ui.card-head id="rq-more" title="Ещё ждут решения" />
        <x-ui.list>
            @foreach ($others as $o)
                <x-ui.row :href="$o['url']" wire:key="rqo-{{ $o['id'] }}">
                    <x-ui.text :title="$o['title']" :sub="$o['meta']" />
                </x-ui.row>
            @endforeach
        </x-ui.list>
    </x-ui.card>
@endif

<x-ui.card aria-labelledby="rq-done">
    <x-ui.card-head id="rq-done" title="Решено раньше" />
    @if ($done->isEmpty())
        <p class="text-t2 text-muted">Пока пусто — здесь появятся запросы, по которым вы приняли решение.</p>
    @else
        <x-ui.list>
            @foreach ($done as $d)
                <x-ui.row align="start" wire:key="rqd-{{ $d['id'] }}">
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="truncate text-t1-s font-medium">{{ $d['title'] }}</span>
                        <span class="text-t2 text-muted">{{ $d['meta'] }}</span>
                        @if ($d['reply'])<span class="text-t2 text-muted">Ответ учителю: «{{ $d['reply'] }}»</span>@endif
                    </div>
                    <span class="shrink-0 whitespace-nowrap text-t2 text-muted">{{ $d['result'] }}</span>
                </x-ui.row>
            @endforeach
        </x-ui.list>
    @endif
</x-ui.card>
