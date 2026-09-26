<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Оплата" :sub="$unpaidCount ? plural_ru($unpaidCount, 'счёт', 'счёта', 'счетов') . ' к оплате' : null" />

    @if (! $hasRecords)
        <x-ui.empty icon="wallet" title="Оплат пока нет" text="Когда появятся занятия к оплате, они будут здесь — вместе с историей оплат." />
    @else
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
            <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
                {{-- Фокус: что оплатить и кому --}}
                @if ($debts->isNotEmpty())
                    <x-ui.card focus aria-labelledby="pay-due">
                        <x-ui.card-head id="pay-due" title="К оплате" />
                        <div class="flex flex-col gap-6">
                            @foreach ($debts as $debt)
                                @php $status = $debt['status']; @endphp
                                <div wire:key="debt-{{ $debt['teacher']?->id }}" @class(['flex flex-col gap-4', 'border-t border-line pt-6' => ! $loop->first])>
                                    <div class="flex items-center gap-3">
                                        <x-ui.avatar :user="$debt['teacher']" />
                                        <div class="flex min-w-0 flex-col gap-1">
                                            <span class="truncate text-t1-s font-medium">{{ $debt['teacher']?->name ?? 'Учитель' }}</span>
                                            <span class="text-t3 text-muted">Ваш учитель</span>
                                        </div>
                                    </div>

                                    @if ($status && $status['blocked'])
                                        <p class="text-t2 text-muted"><x-ui.em>Вход на занятия закрыт</x-ui.em> — откроется сам, как только учитель отметит оплату.</p>
                                    @elseif ($status && $status['lessons_left'] <= 1)
                                        <p class="text-t2 text-muted">Срок оплаты прошёл. Если оплата не будет отмечена, вход на занятия закроется <x-ui.em>после следующего занятия</x-ui.em></p>
                                    @elseif ($status)
                                        <p class="text-t2 text-muted">Срок оплаты прошёл. Если оплата не будет отмечена, вход на занятия закроется через <x-ui.em>{{ plural_ru($status['lessons_left'], 'занятие', 'занятия', 'занятий') }}</x-ui.em></p>
                                    @endif

                                    <x-ui.list>
                                        @foreach ($debt['rows'] as $row)
                                            <x-ui.row>
                                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                                    <span class="text-t1 font-medium">{{ $row['title'] }}</span>
                                                    @if ($row['due'])
                                                        <span class="text-t2 text-muted">@if ($row['urgent'])<x-ui.em>{{ $row['due'] }}</x-ui.em>@else{{ $row['due'] }}@endif</span>
                                                    @endif
                                                </div>
                                                @if ($row['overdue'])<x-ui.badge tone="danger">Просрочено</x-ui.badge>@endif
                                            </x-ui.row>
                                        @endforeach
                                    </x-ui.list>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-t2 text-muted">Перевели учителю напрямую? Он сам отметит оплату.</p>
                    </x-ui.card>
                @else
                    <x-ui.card focus>
                        <div class="flex flex-col gap-1">
                            <h2 class="text-h2 font-medium">Долгов нет</h2>
                            <p class="text-t2 text-muted">Все занятия оплачены.</p>
                        </div>
                    </x-ui.card>
                @endif

                {{-- История по месяцам: исключения (позже срока, без оплаты), а не «Оплачено» в каждой строке --}}
                @if ($months->isNotEmpty())
                    <x-ui.card aria-labelledby="pay-hist">
                        <x-ui.card-head id="pay-hist" title="История оплат" />
                        <x-ui.list>
                            @foreach ($months as $month)
                                <div wire:key="m-{{ $loop->index }}-{{ $month['title'] }}" x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="flex flex-col border-t border-line">
                                    <button type="button" class="flex items-center gap-4 py-4 text-left" x-on:click="open = ! open" x-bind:aria-expanded="open">
                                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                                            <span class="text-t1 font-medium">{{ $month['title'] }}</span>
                                            <span class="text-t2 text-muted">{{ $month['note'] }}</span>
                                        </span>
                                        <x-ui.icon name="chevron-right" class="text-faint transition-transform" x-bind:class="open && 'rotate-90'" />
                                    </button>
                                    <div x-show="open" @if (! $loop->first) x-cloak @endif class="flex flex-col pb-4">
                                        @foreach ($month['rows'] as $row)
                                            <div class="flex items-center gap-4 border-t border-line py-3 pl-4">
                                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                                    <span class="text-t1-s font-medium">{{ $row['title'] }}</span>
                                                    <span class="text-t2 text-muted">{{ $row['sub'] }}</span>
                                                </div>
                                                @if ($row['waived'])<x-ui.badge>Без оплаты</x-ui.badge>@endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </x-ui.list>
                    </x-ui.card>
                @endif
            </div>

            {{-- Как оплатить: напрямую учителю --}}
            <x-ui.card aria-labelledby="pay-how">
                <x-ui.card-head id="pay-how" title="Как оплатить" />
                <x-ui.list>
                    @foreach ($teachers as $t)
                        <x-ui.row :chevron="false" align="start" wire:key="how-{{ $t['id'] }}">
                            <x-ui.avatar :name="$t['name']" :id="$t['id']" />
                            <div class="flex min-w-0 flex-1 flex-col gap-2">
                                <span class="truncate text-t1-s font-medium">{{ $t['name'] }}</span>
                                @if ($t['contacts'])
                                    <div class="flex flex-wrap gap-x-3 gap-y-1 text-t2">
                                        @foreach ($t['contacts'] as $c)
                                            <a href="{{ $c['href'] }}" class="link" @if ($c['external']) target="_blank" rel="noopener" @endif>{{ $c['label'] }}</a>
                                        @endforeach
                                    </div>
                                @endif
                                <x-ui.btn size="s" :href="$t['chat']" class="self-start">Написать учителю</x-ui.btn>
                            </div>
                        </x-ui.row>
                    @endforeach
                </x-ui.list>
                <p class="text-t2 text-muted">Не успеваете оплатить — напишите учителю, он может перенести срок.</p>
            </x-ui.card>
        </div>
    @endif
</div>
