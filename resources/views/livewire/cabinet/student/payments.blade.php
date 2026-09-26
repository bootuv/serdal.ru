<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Оплата" :sub="$unpaidCount ? plural_ru($unpaidCount, 'счёт', 'счёта', 'счетов') . ' к оплате' : null" />

    @if (! $hasRecords)
        <x-ui.empty icon="wallet" title="Оплат пока нет" text="Когда появятся занятия к оплате, они будут здесь — вместе с историей оплат." />
    @else
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
            <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
                {{-- Фокус: что оплатить и кому. Одна жёлтая кнопка — у первого учителя, о котором ещё можно сообщить. --}}
                @if ($debts->isNotEmpty())
                    @php $primaryKey = $debts->firstWhere('canReport', true)['teacherId'] ?? null; @endphp
                    <x-ui.card focus aria-labelledby="pay-due">
                        <x-ui.card-head id="pay-due" title="К оплате" />
                        <div class="flex flex-col gap-6">
                            @foreach ($debts as $debt)
                                @php $status = $debt['status']; @endphp
                                <div wire:key="debt-{{ $debt['teacherId'] }}" @class(['flex flex-col gap-4', 'border-t border-line pt-6' => ! $loop->first])>
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <x-ui.avatar :user="$debt['teacher']" />
                                            <div class="flex min-w-0 flex-col gap-1">
                                                <span class="truncate text-t1-s font-medium">{{ $debt['teacher']?->name ?? 'Учитель' }}</span>
                                                <span class="text-t3 text-muted">{{ $debt['count'] }}@if ($debt['due']) · до {{ $debt['due'] }}@endif</span>
                                            </div>
                                        </div>
                                        @if ($debt['total'])<span class="shrink-0 text-num font-medium">{{ $debt['total'] }}</span>@endif
                                    </div>

                                    @if ($status && $status['blocked'])
                                        <p class="text-t2 text-muted"><x-ui.em>Вход на занятия закрыт</x-ui.em> — откроется, как только учитель подтвердит оплату.</p>
                                    @elseif ($status && $status['lessons_left'] <= 1)
                                        <p class="text-t2 text-muted">Срок оплаты прошёл. Следующее занятие пройдёт как обычно, а после него вход закроется <x-ui.em>до оплаты</x-ui.em></p>
                                    @elseif ($status)
                                        <p class="text-t2 text-muted">Срок оплаты прошёл. Если оплата не будет подтверждена, вход на занятия закроется через <x-ui.em>{{ plural_ru($status['lessons_left'], 'занятие', 'занятия', 'занятий') }}</x-ui.em></p>
                                    @endif

                                    @if ($debt['rejected'])
                                        <p class="text-t2 text-muted"><x-ui.em>Учитель не подтвердил оплату</x-ui.em>@if ($debt['rejected']['reason']): «{{ $debt['rejected']['reason'] }}»@endif. Напишите учителю или сообщите об оплате ещё раз.</p>
                                    @endif

                                    <x-ui.list>
                                        @foreach ($debt['rows'] as $row)
                                            <x-ui.row wire:key="due-{{ $row['id'] }}">
                                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                                    <span class="text-t1 font-medium">{{ $row['title'] }}</span>
                                                    @if ($row['claimed'])
                                                        <span class="text-t2 text-muted">Отправлено учителю · ждёт подтверждения</span>
                                                    @elseif ($row['due'])
                                                        <span class="text-t2 text-muted">@if ($row['urgent'])<x-ui.em>{{ $row['due'] }}</x-ui.em>@else{{ $row['due'] }}@endif</span>
                                                    @endif
                                                </div>
                                                @if ($row['overdue'] && ! $row['claimed'])<x-ui.badge tone="danger">Просрочено</x-ui.badge>@endif
                                                @if ($row['amount'])<span class="shrink-0 text-t1 font-medium">{{ $row['amount'] }}</span>@endif
                                            </x-ui.row>
                                        @endforeach
                                    </x-ui.list>

                                    @if ($debt['canReport'] || $debt['waiting'])
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                            @if ($debt['canReport'])
                                                <x-ui.btn :variant="$debt['teacherId'] === $primaryKey ? 'primary' : 'outline'" wire:click="openReport({{ $debt['teacherId'] }})">Сообщить об оплате</x-ui.btn>
                                            @endif
                                            @if ($debt['waiting'])
                                                <span class="text-t2 text-muted">Отправлено учителю · ждёт подтверждения</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <p class="text-t2 text-muted">Платите учителю напрямую, как договорились, а потом сообщите об оплате — учитель проверит и подтвердит.</p>
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
                                <x-ui.disclosure :title="$month['title']" :meta="$month['note']" :open="$loop->first" wire:key="m-{{ $loop->index }}-{{ $month['title'] }}">
                                    @foreach ($month['rows'] as $row)
                                        <div class="flex items-center gap-4 border-t border-line py-3 pl-4">
                                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                                <span class="text-t1-s font-medium">{{ $row['title'] }}</span>
                                                <span class="text-t2 text-muted">{{ $row['sub'] }}</span>
                                            </div>
                                            @if ($row['waived'])<x-ui.badge>Без оплаты</x-ui.badge>@endif
                                            @if ($row['amount'])<span class="shrink-0 text-t1-s font-medium">{{ $row['amount'] }}</span>@endif
                                        </div>
                                    @endforeach
                                </x-ui.disclosure>
                            @endforeach
                        </x-ui.list>
                    </x-ui.card>
                @endif
            </div>

            {{-- Как вы платите: цена и срок у каждого учителя, контакты для перевода --}}
            <x-ui.card aria-labelledby="pay-how">
                <x-ui.card-head id="pay-how" title="Как вы платите" />
                <x-ui.list>
                    @foreach ($teachers as $t)
                        <x-ui.row :chevron="false" align="start" wire:key="how-{{ $t['id'] }}">
                            <x-ui.avatar :name="$t['name']" :id="$t['id']" />
                            <div class="flex min-w-0 flex-1 flex-col gap-2">
                                <div class="flex min-w-0 flex-col gap-1">
                                    <span class="truncate text-t1-s font-medium">{{ $t['name'] }}</span>
                                    @if ($t['terms']['free'])
                                        <span class="text-t2 text-muted">Бесплатно</span>
                                    @elseif ($t['terms']['price'])
                                        <span class="text-t2 text-muted"><x-ui.em>{{ $t['terms']['price'] }}</x-ui.em> {{ $t['terms']['unit'] }} · {{ $t['terms']['due'] }}</span>
                                    @else
                                        <span class="text-t2 text-muted">Оплата {{ $t['terms']['unit'] }} · {{ $t['terms']['due'] }}</span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap gap-x-3 gap-y-1 text-t2">
                                    @foreach ($t['contacts'] as $c)
                                        <a href="{{ $c['href'] }}" class="link" {!! $c['external'] ? 'target="_blank" rel="noopener"' : '' !!}>{{ $c['label'] }}</a>
                                    @endforeach
                                    <a href="{{ $t['chat'] }}" class="link">Написать учителю</a>
                                </div>
                            </div>
                        </x-ui.row>
                    @endforeach
                </x-ui.list>
                <p class="text-t2 text-muted">Не успеваете оплатить — напишите учителю, он может перенести срок.</p>
            </x-ui.card>
        </div>
    @endif

    {{-- Окно «Сообщить об оплате»: занятия, чек, комментарий --}}
    @if ($report)
        <x-ui.modal title="Сообщить об оплате" :sub="$report['teacher']?->name" close="closeReport">
            <div class="flex flex-col gap-2">
                <span class="text-t2 font-medium">Что вы оплатили</span>
                <div class="flex flex-col gap-2" role="group" aria-label="Занятия">
                    @foreach ($report['rows'] as $row)
                        <x-ui.option value="{{ $row['id'] }}" wire:model.live="reportSelected" wire:key="rep-{{ $row['id'] }}">
                            <span class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="truncate text-t1-s font-medium">{{ $row['title'] }}</span>
                                @if ($row['hint'])<span @class(['text-t2', 'font-semibold text-ink' => $row['overdue'], 'text-muted' => ! $row['overdue']])>{{ $row['hint'] }}</span>@endif
                            </span>
                            @if ($row['amount'])<span class="shrink-0 text-t1-s font-medium">{{ $row['amount'] }}</span>@endif
                        </x-ui.option>
                    @endforeach
                </div>
                @error('reportSelected')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            </div>
            @if ($report['sum'])
                <div class="flex items-center justify-between gap-4 border-t border-line pt-4">
                    <span class="text-t1 font-medium">Итого</span>
                    <span class="text-h2 font-medium">{{ $report['sum'] }}</span>
                </div>
            @endif

            <div class="flex flex-col gap-2">
                <span class="text-t2 font-medium">Чек</span>
                @unless ($report['maxFiles'])
                    <x-ui.dropzone title="Приложите фото или файл чека" hint="JPG, PNG, HEIC или PDF до 10 МБ"
                                   accept=".jpg,.jpeg,.png,.heic,.heif,.pdf,image/jpeg,image/png,image/heic,image/heif,application/pdf"
                                   wire:model="picked" aria-label="Приложите фото или файл чека" />
                @endunless
                <span class="text-t2 text-muted" wire:loading wire:target="picked">Загружаем…</span>
                @error('picked.*')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                @error('receipts')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                @error('receipts.*')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                @foreach ($report['files'] as $i => $file)
                    <div class="flex items-center gap-3" wire:key="receipt-{{ $i }}-{{ $file['name'] }}">
                        <x-ui.file-tile :name="$file['name']" />
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="truncate text-t1-s font-medium">{{ $file['name'] }}</span>
                            @if ($file['meta'])<span class="text-t3 text-muted">{{ $file['meta'] }}</span>@endif
                        </div>
                        <x-ui.btn size="s" square icon="x" wire:click="removeReceipt({{ $i }})" aria-label="Убрать файл" />
                    </div>
                @endforeach
            </div>

            <x-ui.field label="Комментарий" name="reportComment" :rows="3" optional wire:model="reportComment" placeholder="Например: перевела по номеру телефона" />

            <x-slot:note>Учитель проверит и подтвердит оплату</x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="closeReport">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="sendReport" wire:loading.attr="disabled" wire:target="sendReport,picked" :disabled="empty($reportSelected)">Отправить учителю</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
