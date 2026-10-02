<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head :title="$heading" :sub="$facts" :back="route('cabinet.admin.mailings')" back-label="Рассылки">
        <x-slot:actions>
            @if ($editable)
                <div class="hidden items-center gap-2 lg:flex">
                    @if (in_array($status, ['new', 'draft'], true))
                        <x-ui.btn wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft,submit">Сохранить черновик</x-ui.btn>
                    @endif
                    <x-ui.btn variant="primary" wire:click="submit" wire:loading.attr="disabled" wire:target="saveDraft,submit">{{ $submitLabel }}</x-ui.btn>
                </div>
            @else
                @if ($status === 'sending')
                    <x-ui.btn wire:click="askStop">Остановить отправку</x-ui.btn>
                @endif
                <x-ui.btn wire:click="duplicate">Сделать копию</x-ui.btn>
            @endif
        </x-slot:actions>
    </x-ui.page-head>

    @if ($editable)
        {{-- Телефон: действия шапки под заголовком --}}
        <div class="flex flex-wrap items-center gap-2 lg:hidden">
            <x-ui.btn variant="primary" wire:click="submit" wire:loading.attr="disabled" wire:target="saveDraft,submit">{{ $submitLabel }}</x-ui.btn>
            @if (in_array($status, ['new', 'draft'], true))
                <x-ui.btn wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft,submit">Сохранить черновик</x-ui.btn>
            @endif
        </div>

        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-12">
            <div class="flex w-full min-w-0 flex-col gap-6 lg:w-form lg:shrink-0">
                <x-ui.field label="Тема" name="subject" wire:model.live.debounce.500ms="subject" placeholder="Например, «Онлайн-занятия для ваших учителей»" />
                <x-ui.field label="Строка рядом с темой" name="preheader" wire:model="preheader" optional hint="Почта показывает её во входящих после темы" />

                <x-ui.editor-media label="Текст" name="body" wire:model="body" upload-model="image" upload-method="storeImage"
                                   hint="Подставится из списка: {школа}, {город}, {имя} — имя и отчество пользователя. Если поля нет — {имя|коллеги}" />

                <div class="flex flex-col gap-4 sm:flex-row">
                    <div class="sm:basis-1/2"><x-ui.field label="Кнопка" name="buttonText" wire:model="buttonText" optional placeholder="Например, «Попробовать бесплатно»" /></div>
                    <div class="sm:basis-1/2"><x-ui.field label="Ссылка кнопки" name="buttonUrl" wire:model="buttonUrl" type="url" /></div>
                </div>

                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <button type="button" class="link text-t2" wire:click="openPreview">Посмотреть письмо</button>
                    @if ($status === 'scheduled')
                        <button type="button" class="link text-t2" wire:click="unschedule">Отменить отправку</button>
                    @endif
                    @if ($model)
                        <button type="button" class="link text-t2" wire:click="askDelete">Удалить письмо</button>
                    @endif
                </div>
            </div>

            <div class="flex min-w-0 flex-1 flex-col gap-6">
                <x-ui.card aria-labelledby="ml-who">
                    <x-ui.card-head id="ml-who" title="Кому" />
                    @forelse ($lists as $list)
                        <x-ui.option wire:key="mlo-{{ $list->id }}" wire:model.live="listIds" value="{{ $list->id }}" :title="$list->name"
                                     :sub="plural_ru($list->active, 'адрес', 'адреса', 'адресов')" />
                    @empty
                        <p class="text-t2 text-muted">Списков пока нет — <a href="{{ route('cabinet.admin.mailings', ['tab' => 'lists']) }}" class="link">создайте список</a> и загрузите в него адреса</p>
                    @endforelse
                    @error('listIds')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                    @if ($lists->isNotEmpty())
                        <span class="text-t2 text-muted">
                            @if ($count)
                                Получат {{ plural_ru($count, 'адрес', 'адреса', 'адресов') }} — каждый один раз, без отписавшихся
                            @else
                                Отметьте списки, которым отправить письмо
                            @endif
                        </span>
                    @endif
                </x-ui.card>

                <x-ui.card aria-labelledby="ml-test">
                    <x-ui.card-head id="ml-test" title="Пробное письмо" />
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                        <div class="min-w-0 flex-1"><x-ui.field label="Почта" name="testEmail" type="email" wire:model="testEmail" hint="Придёт так, как увидит первый адрес из списков" /></div>
                        <x-ui.btn class="self-start sm:mt-6" icon="send" wire:click="sendTest" wire:loading.attr="disabled" wire:target="sendTest">Отправить</x-ui.btn>
                    </div>
                </x-ui.card>

                <x-ui.card aria-labelledby="ml-when">
                    <x-ui.card-head id="ml-when" title="Когда отправить" />
                    <x-ui.seg :items="['now' => 'Сразу', 'later' => 'По времени']" model="when" :active="$when" aria-label="Когда отправить" />
                    @if ($when === 'later')
                        <x-ui.field label="Дата и время" name="sendAt" type="datetime-local" wire:model="sendAt" hint="По московскому времени" />
                    @endif
                    <span class="text-t2 text-muted">Письма уходят по {{ $perMinute }} в минуту, чтобы почтовые службы не сочли рассылку спамом</span>
                </x-ui.card>
            </div>
        </div>
    @else
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-12" {!! $status === 'sending' ? 'wire:poll.15s' : '' !!}>
            <div class="flex w-full min-w-0 flex-col gap-6 lg:w-form lg:shrink-0">
                <x-ui.card focus aria-labelledby="ml-stats">
                    <x-ui.card-head id="ml-stats" title="Итоги">
                        <x-slot:action><span class="text-t2 text-muted">отправлено <x-ui.em>{{ $stats['sent'] }}</x-ui.em> из {{ $stats['total'] }}</span></x-slot:action>
                    </x-ui.card-head>
                    <x-ui.progress on-mint :value="$stats['sent']" :max="max(1, $stats['total'])" label="Отправлено писем" />
                    @if ($status === 'sending' && $model->error)
                        <p class="text-t2"><x-ui.em danger>Отправка стоит:</x-ui.em> {{ $model->error }}. Повторим через минуту — или остановите отправку.</p>
                    @endif
                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        @foreach ([['Открыли', $stats['opened'], $percent($stats['opened'])], ['Перешли', $stats['clicked'], $percent($stats['clicked'])], ['Отписались', $stats['unsubscribed'], null], ['Не доставлено', $stats['failed'], null]] as [$label, $value, $share])
                            <div class="flex flex-col gap-1">
                                <dt class="text-t2 text-muted">{{ $label }}</dt>
                                <dd class="text-num font-medium">{{ $value }}@if ($share)<span class="text-t2 font-normal text-muted"> · {{ $share }}</span>@endif</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-ui.card>

                <x-ui.card class="gap-4" aria-labelledby="ml-to">
                    <x-ui.card-head id="ml-to" title="Адресаты" />
                    <div class="flex flex-col gap-3">
                        <x-ui.seg fit :items="$filters" model="filter" :active="$filter" aria-label="Каких адресатов показать" />
                        <x-ui.search placeholder="Почта" wire:model.live.debounce.400ms="search" full />
                    </div>
                    <div class="flex flex-col">
                        @forelse ($deliveries as $d)
                            <x-ui.row wire:key="md-{{ $d['id'] }}" class="first:border-t-0 first:pt-0" align="start">
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="truncate text-t1-s font-medium">{{ $d['email'] }}</span>
                                    @if ($d['sub'])<span class="truncate text-t2 text-muted">{{ $d['sub'] }}</span>@endif
                                    @if ($d['status']['note'])<span class="text-t3 text-muted">{{ $d['status']['note'] }}</span>@endif
                                </div>
                                @if ($d['status']['tone'])
                                    <x-ui.badge :tone="$d['status']['tone']">{{ $d['status']['text'] }}</x-ui.badge>
                                @elseif ($d['status']['text'])
                                    <span class="shrink-0 text-t2 text-muted">{{ $d['status']['text'] }}</span>
                                @endif
                            </x-ui.row>
                        @empty
                            <p class="text-t2 text-muted">Никого — попробуйте другой фильтр</p>
                        @endforelse
                    </div>
                    @if ($more > 0)
                        <button type="button" wire:click="more" class="link self-start text-t1-s">Показать ещё {{ min($more, 50) }}</button>
                    @endif
                </x-ui.card>
            </div>

            <div class="flex min-w-0 flex-1 flex-col gap-6">
                <x-ui.card aria-labelledby="ml-letter">
                    <x-ui.card-head id="ml-letter" title="Письмо">
                        <x-slot:action><x-ui.btn size="s" icon="eye" wire:click="openPreview">Посмотреть</x-ui.btn></x-slot:action>
                    </x-ui.card-head>
                    <div class="flex flex-col gap-1">
                        <span class="text-t1-s font-medium">{{ $listNames->implode(', ') ?: 'Списки удалены' }}</span>
                        <span class="text-t2 text-muted">Отправлено с {{ config('mail.newsletter.from.address') }}</span>
                    </div>
                </x-ui.card>

                @if ($links->isNotEmpty())
                    <x-ui.card class="gap-0" aria-labelledby="ml-links">
                        <x-ui.card-head id="ml-links" title="Переходы по ссылкам" class="mb-4" />
                        @foreach ($links as $link)
                            <x-ui.row wire:key="mll-{{ $link->id }}" class="first:border-t-0 first:pt-0">
                                <a href="{{ $link->url }}" target="_blank" rel="noopener" class="min-w-0 flex-1 truncate text-t2 hover:underline">{{ preg_replace('#^https?://(www\.)?#', '', $link->url) }}</a>
                                <span class="shrink-0 text-t2 text-muted">{{ plural_ru($link->clicks, 'переход', 'перехода', 'переходов') }}</span>
                            </x-ui.row>
                        @endforeach
                    </x-ui.card>
                @endif

                @if ($status !== 'sending')
                    <button type="button" class="link self-start text-t2" wire:click="askDelete">Удалить письмо</button>
                @endif
            </div>
        </div>
    @endif

    @if ($preview)
        <x-ui.modal title="Письмо" :sub="$heading" close="closePreview" width="l" fill>
            <iframe srcdoc="{{ $previewHtml }}" title="Как выглядит письмо" sandbox="allow-popups allow-popups-to-escape-sandbox" class="size-full rounded-lg shadow-outline"></iframe>
        </x-ui.modal>
    @endif

    @if ($confirmSend)
        <x-ui.modal title="Отправить письмо?" :sub="$heading" close="closeSend" width="s">
            <p class="text-t1-s">Получат {{ plural_ru($count, 'адрес', 'адреса', 'адресов') }}. Письма уходят по {{ $perMinute }} в минуту — вся рассылка займёт {{ $duration }}.</p>
            <span class="text-t2 text-muted">Остановить можно в любой момент, но уже отправленные письма не вернуть.</span>
            <x-slot:footer>
                <x-ui.btn wire:click="closeSend">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="send" wire:loading.attr="disabled" wire:target="send">Отправить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($confirmStop)
        <x-ui.modal title="Остановить отправку?" :sub="$heading" close="closeStop" width="s">
            <p class="text-t1-s">Письма, которые ещё не ушли, не отправятся. Продолжить потом не получится — только сделать копию и отправить заново.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="closeStop">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="stop">Остановить отправку</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($confirmDelete && $model)
        <x-ui.modal title="Удалить письмо?" :sub="$heading" close="closeDelete" width="s">
            <p class="text-t1-s">{{ $editable ? 'Черновик пропадёт.' : 'Пропадёт и статистика: кто открыл и перешёл по ссылкам.' }} Вернуть его нельзя.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="closeDelete">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="delete">Удалить письмо</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
