{{-- Админка · страница занятия (макет AdminLessons, страница занятия). --}}
<div class="grid grid-cols-1 gap-6 lg:gap-8">
    <div class="flex flex-col gap-4">
        <a href="{{ $backUrl }}" class="inline-flex items-center gap-2 self-start text-t1-s font-medium text-muted hover:text-ink"><x-ui.icon name="arrow-left" size="s" />Занятия</a>
        <header class="flex flex-col gap-4">
            <h1 class="text-h1-m font-medium lg:text-h1">{{ $room->name }}</h1>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <p class="text-t1 text-muted">{{ $facts }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($state === 'live')
                        <x-ui.btn size="l" wire:click="ask('stop')">Завершить занятие</x-ui.btn>
                        <x-ui.btn variant="primary" size="l" icon="video" :href="$joinUrl" target="_blank" rel="noopener">Подключиться</x-ui.btn>
                    @elseif ($state === 'arch')
                        <x-ui.btn size="l" wire:click="ask('delete')">Удалить навсегда</x-ui.btn>
                        <x-ui.btn size="l" wire:click="restore">Вернуть из архива</x-ui.btn>
                    @else
                        <x-ui.btn size="l" wire:click="ask('archive')">В архив</x-ui.btn>
                    @endif
                </div>
            </div>
        </header>
    </div>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
            <x-ui.card :focus="$state === 'live'" aria-labelledby="lp-who">
                <x-ui.card-head id="lp-who" title="Кто занимается">
                    @if ($liveLen)
                        <x-slot:action><span class="text-t2 text-muted">{{ $liveLen }}</span></x-slot:action>
                    @endif
                </x-ui.card-head>
                <x-ui.list>
                    @if ($teacher)
                        <x-ui.row :href="$teacherUrl" :chevron="(bool) $teacherUrl">
                            <x-ui.avatar :user="$teacher" />
                            <x-ui.text :title="$teacher->name" :sub="$teacherNote" />
                        </x-ui.row>
                    @endif
                    @forelse ($people as $p)
                        <x-ui.row :href="$p['url']" :chevron="(bool) $p['url']" wire:key="lp-p-{{ $p['id'] }}">
                            <x-ui.avatar :name="$p['name']" :id="$p['id']" />
                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="truncate text-t1 font-medium">{{ $p['name'] }}</span>
                                @if ($p['note'])<span @class(['text-t2', 'font-semibold text-ink' => $p['away'], 'text-muted' => ! $p['away']])>{{ $p['note'] }}</span>@endif
                            </div>
                        </x-ui.row>
                    @empty
                        <p class="border-t border-line pt-4 text-t2 text-muted">Учеников в занятии нет.</p>
                    @endforelse
                </x-ui.list>
            </x-ui.card>

            <x-ui.card aria-labelledby="lp-ses">
                <x-ui.card-head id="lp-ses" title="Проведённые занятия">
                    @if ($sessionsTotal)
                        <x-slot:action><a href="{{ $sessionsUrl }}" class="link text-t2">Все {{ $sessionsTotal }}</a></x-slot:action>
                    @endif
                </x-ui.card-head>
                @if ($sessions->isEmpty())
                    <p class="text-t2 text-muted">Пока ни одного — занятие ещё не проводилось.</p>
                @else
                    <x-ui.list>
                        @foreach ($sessions as $s)
                            <x-ui.row :href="$s['url']" :chevron="(bool) $s['url']" wire:key="lp-s-{{ $s['key'] }}">
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="truncate text-t1-s font-medium">{{ $s['when'] }}</span>
                                    <span class="text-t2 text-muted">{{ $s['sub'] }}</span>
                                </div>
                                @if ($s['request'])<x-ui.badge>Запрос на удаление</x-ui.badge>@endif
                            </x-ui.row>
                        @endforeach
                    </x-ui.list>
                @endif
            </x-ui.card>
        </div>

        <div class="flex min-w-0 flex-col gap-6">
            <x-ui.card :focus="$state === 'plan'" aria-labelledby="lp-sch">
                <x-ui.card-head id="lp-sch" title="Расписание" />
                @if ($rules->isNotEmpty())
                    @foreach ($rules as $rule)
                        <div class="flex flex-col gap-1">
                            <span class="text-t1-s font-medium">{{ $rule['rule'] }}</span>
                            <span class="text-t2 text-muted">{{ $rule['since'] }}</span>
                        </div>
                    @endforeach
                    @if ($upcoming->isNotEmpty())
                        <x-ui.list>
                            @foreach ($upcoming as $n)
                                <x-ui.row>
                                    <span @class(['min-w-0 flex-1 truncate text-t1-s font-medium', 'text-muted line-through' => $n['cancelled']])>{{ $n['when'] }}</span>
                                    @if ($n['note'])<span @class(['shrink-0 text-t2', 'font-semibold text-ink' => ! $n['cancelled'], 'text-muted' => $n['cancelled']])>{{ $n['note'] }}</span>@endif
                                </x-ui.row>
                            @endforeach
                        </x-ui.list>
                    @endif
                @else
                    <p class="text-t2 text-muted">{{ $noRuleText }}</p>
                @endif
            </x-ui.card>

            <x-ui.card aria-labelledby="lp-files">
                <x-ui.card-head id="lp-files" title="Презентации" />
                @if ($files->isEmpty())
                    <p class="text-t2 text-muted">Учитель не загружал презентации.</p>
                @else
                    <x-ui.list>
                        @foreach ($files as $f)
                            <x-ui.row :href="$f['url']" :chevron="(bool) $f['url']" target="_blank" rel="noopener" wire:key="lp-f-{{ $loop->index }}">
                                <x-ui.file-tile :name="$f['name']" />
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="truncate text-t1-s font-medium">{{ $f['name'] }}</span>
                                    @if ($f['meta'])<span class="text-t2 text-muted">{{ $f['meta'] }}</span>@endif
                                </div>
                            </x-ui.row>
                        @endforeach
                    </x-ui.list>
                @endif
            </x-ui.card>
        </div>
    </div>

    @if ($confirm === 'stop')
        <x-ui.modal title="Завершить занятие?" :sub="$room->name" width="s" close="closeConfirm">
            <p class="text-t1">Класс закроется у всех, кто сейчас в нём: у учителя{{ $modal['inClass'] }}. Занятие попадёт в проведённые, запись — в «Записи» после обработки.</p>
            <p class="text-t2 text-muted">{{ $modal['teacher'] }} получит уведомление, что занятие завершил администратор.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="closeConfirm">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="stop" wire:loading.attr="disabled" wire:target="stop">Завершить занятие</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($confirm === 'archive')
        <x-ui.modal title="Перенести в архив?" :sub="$room->name" width="s" close="closeConfirm">
            <p class="text-t1">Занятие пропадёт у учителя и учеников, а расписание <x-ui.em>удалится</x-ui.em>. Проведённые занятия и записи сохранятся.</p>
            <p class="text-t2 text-muted">Вернуть из архива можно, но время придётся назначить заново.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="closeConfirm">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="archive">В архив</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($confirm === 'delete')
        <x-ui.modal title="Удалить навсегда?" :sub="$room->name" width="s" close="closeConfirm">
            <p class="text-t1">{{ $modal['delWhat'] }}. <x-ui.em>Вернуть нельзя.</x-ui.em></p>
            <p class="text-t2 text-muted">Записи и задания останутся.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="closeConfirm">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="forceDelete" wire:loading.attr="disabled" wire:target="forceDelete">Удалить навсегда</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
