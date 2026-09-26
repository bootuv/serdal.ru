<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Задания" :sub="$subtitle" />

    <div class="flex flex-col gap-6">
        <x-ui.tabs model="tab" :active="$tab" :items="['actual' => 'Актуальные', 'done' => 'Сданные', 'all' => 'Все']"
                   :counts="['actual' => $actualCount]" aria-label="Какие задания показать" />

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
            <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
                @if ($tab === 'actual')
                    @if ($focus)
                        {{-- Фокус-блок: задание с ближайшим сроком --}}
                        <x-ui.card focus class="lg:flex-row lg:items-center lg:justify-between lg:gap-6" aria-label="Ближе всего по сроку">
                            <div class="flex min-w-0 flex-col gap-4">
                                <p class="text-t2 text-muted">
                                    @if ($focus['state'] === 'revision')
                                        Вернули на доработку@if ($focus['em']) · <x-ui.em>{{ $focus['em'] }}</x-ui.em>@endif
                                    @elseif ($focus['state'] === 'overdue')
                                        Срок прошёл · <x-ui.em>{{ $focus['deadline'] }}</x-ui.em>
                                    @elseif ($focus['em'])
                                        Ближайший срок · <x-ui.em>{{ $focus['em'] }}</x-ui.em>
                                    @else
                                        Нужно сдать
                                    @endif
                                </p>
                                <div class="flex flex-col gap-1">
                                    <h2 class="text-h2 font-medium lg:text-num">{{ $focus['title'] }}</h2>
                                    @if ($focus['room'])<p class="text-t1 text-muted">{{ $focus['room'] }}</p>@endif
                                </div>
                                @if ($focus['teacher'])
                                    <div class="flex items-center gap-3">
                                        <x-ui.avatar :name="$focus['teacher']" :id="$focus['teacherId']" />
                                        <div class="flex flex-col gap-1">
                                            <span class="text-t1-s font-medium">{{ $focus['teacher'] }}</span>
                                            <span class="text-t3 text-muted">Ваш учитель</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="flex shrink-0 flex-col gap-3 lg:items-end">
                                <x-ui.btn variant="primary" size="l" :href="$focus['url']">{{ $focus['state'] === 'revision' ? 'Пересдать работу' : 'Сдать работу' }}</x-ui.btn>
                                @if ($focus['hasFiles'])
                                    <a href="{{ $focus['url'] }}#files" class="link self-start text-t2 lg:self-end">Файлы от учителя</a>
                                @endif
                            </div>
                        </x-ui.card>

                        @if ($actual->isNotEmpty())
                            <x-ui.card aria-labelledby="tasks-more">
                                <x-ui.card-head id="tasks-more" title="Ещё нужно сдать" />
                                <x-ui.list>
                                    @foreach ($actual as $item)
                                        @include('livewire.cabinet.student.partials.task-row', ['item' => $item])
                                    @endforeach
                                </x-ui.list>
                            </x-ui.card>
                        @endif
                    @elseif ($doneCount > 0)
                        <x-ui.card focus>
                            <x-ui.empty icon="check" title="Всё сдано" text="Новые задания от учителя появятся здесь." class="py-8" />
                        </x-ui.card>
                    @else
                        <x-ui.card focus>
                            <x-ui.empty icon="tasks" title="Заданий пока нет" text="Когда учитель задаст работу, она появится здесь." class="py-8" />
                        </x-ui.card>
                    @endif
                @else
                    @if ($tab === 'all' && $actual->isNotEmpty())
                        <x-ui.card aria-labelledby="tasks-todo">
                            <x-ui.card-head id="tasks-todo" title="Нужно сдать" />
                            <x-ui.list>
                                @foreach ($actual as $item)
                                    @include('livewire.cabinet.student.partials.task-row', ['item' => $item])
                                @endforeach
                            </x-ui.list>
                        </x-ui.card>
                    @endif

                    @if ($done->isNotEmpty())
                        <x-ui.card aria-labelledby="tasks-done">
                            <x-ui.card-head id="tasks-done" :title="$tab === 'all' ? 'Сданные' : 'Сданные работы'" />
                            <x-ui.list>
                                @foreach ($done as $item)
                                    @include('livewire.cabinet.student.partials.task-row', ['item' => $item])
                                @endforeach
                            </x-ui.list>
                            @if ($hasMore)
                                <x-ui.btn class="self-start" wire:click="showMore">Показать ещё</x-ui.btn>
                            @endif
                        </x-ui.card>
                    @elseif ($tab === 'done' || $actual->isEmpty())
                        <x-ui.card>
                            <x-ui.empty icon="tasks" :title="$tab === 'done' ? 'Сданных работ пока нет' : 'Заданий пока нет'"
                                        :text="$tab === 'done' ? 'Отправленные учителю работы и оценки появятся здесь.' : 'Когда учитель задаст работу, она появится здесь.'" class="py-8" />
                        </x-ui.card>
                    @endif
                @endif
            </div>

            {{-- Успеваемость --}}
            @if ($metrics)
                <x-ui.card aria-labelledby="perf">
                    <x-ui.card-head id="perf" title="Успеваемость" />
                    @if ($teachers->count() > 1)
                        <x-ui.seg model="perfTeacherId" :active="$perfTeacherId" :items="$teachers->pluck('name', 'id')->all()" aria-label="Учитель" />
                    @endif
                    <x-ui.rings :metrics="$metrics" stacked />
                    @if ($perfTeacher)<p class="text-t2 text-muted">{{ $perfTeacher }} · за всё время</p>@endif
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
