{{-- Заявки учителей. Макет: AdminApplications. Вкладки по статусу, поиск, окно заявки, одобрение и отказ. --}}
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Заявки учителей" />

    <div class="flex flex-col gap-6">
        {{-- Вкладки и поиск в одной строке с общей линией снизу --}}
        <div class="flex flex-col lg:flex-row lg:items-end">
            <x-ui.tabs model="tab" :active="$tab" :items="$tabs" :counts="['pending' => $pendingCount]" class="flex-1" aria-label="Заявки" />
            <div class="pt-4 lg:border-b lg:border-line lg:pb-2 lg:pl-6 lg:pt-0">
                <x-ui.search wire:model.live.debounce.300ms="q" placeholder="Имя или почта" />
            </div>
        </div>

        <x-ui.card class="gap-0" aria-label="{{ $tabs[$tab] }}">
            @if ($rows->isNotEmpty())
                <div class="hidden items-center gap-4 pb-3 text-t3 font-medium text-muted lg:flex">
                    <span class="grid flex-1 grid-cols-12 gap-4">
                        <span class="col-span-4">Учитель</span>
                        <span class="col-span-2">Предметы</span>
                        <span class="col-span-2">Тариф</span>
                        <span class="col-span-2">Пригласил</span>
                        <span class="col-span-2">{{ $tab === 'pending' ? 'Когда пришла' : 'Когда' }}</span>
                    </span>
                    <span class="w-5 shrink-0"></span>
                </div>
            @endif

            @if ($rows->isEmpty())
                <p class="text-t2 text-muted">{{ $emptyText }}</p>
            @endif
            <x-ui.list>
                @foreach ($rows as $r)
                    <button type="button" wire:click="open({{ $r['id'] }})" wire:key="app-{{ $r['id'] }}"
                            class="group flex w-full items-center gap-4 border-t border-line py-4 text-left last:pb-0">
                        <span class="flex min-w-0 flex-1 flex-col gap-2 lg:grid lg:grid-cols-12 lg:items-center lg:gap-4">
                            <span class="flex min-w-0 items-center gap-3 lg:col-span-4">
                                <x-ui.avatar :name="$r['name']" :id="$r['id']" />
                                <span class="flex min-w-0 flex-col gap-1">
                                    <span class="truncate text-t1 font-medium group-hover:underline group-hover:decoration-line-strong group-hover:underline-offset-4">{{ $r['name'] }}</span>
                                    <span class="truncate text-t2 text-muted">{{ $r['email'] }}</span>
                                </span>
                            </span>
                            <span class="hidden truncate text-t1-s lg:col-span-2 lg:block">{{ $r['subjects'] ?: '—' }}</span>
                            <span class="hidden text-t1-s lg:col-span-2 lg:block">{{ $r['tariff'] }}</span>
                            <span @class(['hidden truncate text-t1-s lg:col-span-2 lg:block', 'text-muted' => ! $r['ref']])>{{ $r['ref'] ?? 'Нет' }}</span>
                            <span class="flex flex-col gap-1 pl-12 lg:col-span-2 lg:pl-0">
                                <span class="whitespace-nowrap text-t1-s font-medium">{{ $r['when'] }}</span>
                                @if ($r['waits'])<span class="text-t2 font-semibold text-ink">{{ $r['waits'] }}</span>@endif
                                @if ($r['note'])<span class="text-t2 text-muted">{{ $r['note'] }}</span>@endif
                            </span>
                        </span>
                        <x-ui.icon name="chevron-right" class="text-faint group-hover:text-ink" />
                    </button>
                @endforeach
            </x-ui.list>
        </x-ui.card>
    </div>

    {{-- Заявка --}}
    @if ($a && $step === 'view')
        <x-ui.modal :title="$a['name']" :sub="$a['sub']" close="close">
            <div class="flex flex-col gap-6">
                @if ($a['reason'])
                    <div class="flex flex-col gap-2 rounded-lg bg-soft p-4">
                        <span class="text-t2 font-medium">Причина отказа</span>
                        <p class="whitespace-pre-line text-t1-s">{{ $a['reason'] }}</p>
                    </div>
                @endif

                <section class="flex flex-col gap-3" aria-labelledby="ap-p">
                    <span id="ap-p" class="text-t3 font-semibold text-muted">Личные данные</span>
                    <dl class="grid grid-cols-3 gap-x-4 gap-y-3 text-t1-s">
                        <dt class="text-muted">ФИО</dt><dd class="col-span-2 min-w-0 break-words">{{ $a['full'] }}</dd>
                        <dt class="text-muted">Почта</dt><dd class="col-span-2 min-w-0 break-words">{{ $a['email'] }}</dd>
                        <dt class="text-muted">Телефон</dt><dd @class(['col-span-2', 'text-muted' => ! $a['phone']])>{{ $a['phone'] ?: 'Не указан' }}</dd>
                        <dt class="text-muted">Telegram</dt><dd @class(['col-span-2 min-w-0 break-words', 'text-muted' => ! $a['telegram']])>{{ $a['telegram'] ?: 'Не указан' }}</dd>
                        <dt class="text-muted">WhatsApp</dt><dd @class(['col-span-2', 'text-muted' => ! $a['whatsapp']])>{{ $a['whatsapp'] ?: 'Не указан' }}</dd>
                    </dl>
                </section>

                @if ($a['about'])
                    <div class="h-px bg-line"></div>
                    <section class="flex flex-col gap-3" aria-labelledby="ap-a">
                        <span id="ap-a" class="text-t3 font-semibold text-muted">О себе</span>
                        <p class="whitespace-pre-line text-t1-s">{{ $a['about'] }}</p>
                    </section>
                @endif

                <div class="h-px bg-line"></div>
                <section class="flex flex-col gap-3" aria-labelledby="ap-w">
                    <span id="ap-w" class="text-t3 font-semibold text-muted">Что преподаёт</span>
                    <dl class="grid grid-cols-3 gap-x-4 gap-y-3 text-t1-s">
                        <dt class="text-muted">Предметы</dt><dd @class(['col-span-2', 'text-muted' => ! $a['subjects']])>{{ $a['subjects'] ?? 'Не указаны' }}</dd>
                        <dt class="text-muted">Направления</dt><dd @class(['col-span-2', 'text-muted' => ! $a['directs']])>{{ $a['directs'] ?? 'Не указаны' }}</dd>
                        <dt class="text-muted">Классы</dt><dd @class(['col-span-2', 'text-muted' => ! $a['grades']])>{{ $a['grades'] ?? 'Не указаны' }}</dd>
                        <dt class="text-muted">Тариф</dt><dd @class(['col-span-2', 'text-muted' => ! $a['tariff']])>{{ $a['tariff'] ?? 'Не выбран' }}</dd>
                        <dt class="text-muted">Пригласил</dt><dd @class(['col-span-2', 'text-muted' => ! $a['ref']])>{{ $a['ref'] ?? 'Пришёл сам' }}</dd>
                    </dl>
                </section>
            </div>

            <x-slot:note>
                @if ($a['userUrl'])<a href="{{ $a['userUrl'] }}" class="link text-t2">Открыть в пользователях</a>@endif
            </x-slot:note>
            <x-slot:footer>
                @if ($a['status'] === 'pending')
                    <x-ui.btn wire:click="toReject">Отклонить</x-ui.btn>
                    <x-ui.btn variant="primary" wire:click="toApprove">Одобрить</x-ui.btn>
                @else
                    <x-ui.btn wire:click="close">Закрыть</x-ui.btn>
                @endif
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Одобрить --}}
    @if ($a && $step === 'approve')
        <x-ui.modal title="Одобрить заявку?" :sub="$a['name']" close="back" width="s">
            <p class="text-t1">Создадим аккаунт учителя. Пароль для входа уйдёт на почту <x-ui.em>{{ $a['email'] }}</x-ui.em>.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="back">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="approve" wire:loading.attr="disabled" wire:target="approve">Одобрить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Почта уже занята --}}
    @if ($a && $step === 'error')
        <x-ui.modal title="Одобрить заявку?" :sub="$a['name']" close="back" width="s">
            <div class="flex flex-col gap-1 rounded-lg bg-danger-bg p-4 text-danger-fg" role="alert">
                <span class="text-t1-s font-medium">Эта почта уже зарегистрирована</span>
                <span class="text-t2">{{ $existing['text'] ?? $a['email'] }}</span>
            </div>
            <p class="text-t1">Попросите прислать заявку с другой почтой или откройте аккаунт в пользователях.</p>
            <x-slot:note>
                @if ($existing['url'] ?? null)<a href="{{ $existing['url'] }}" class="link text-t2">Открыть в пользователях</a>@endif
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="back">Закрыть</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Отклонить --}}
    @if ($a && $step === 'reject')
        <x-ui.modal title="Отклонить заявку?" :sub="$a['name']" close="back" width="s">
            <x-ui.field label="Причина" name="reason" optional :rows="4" wire:model="reason" placeholder="Например: не хватает опыта преподавания"
                        :hint="'Уйдёт в письме об отказе на ' . $a['email']" />
            <x-slot:footer>
                <x-ui.btn wire:click="back">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="reject" wire:loading.attr="disabled" wire:target="reject">Отклонить заявку</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($toast)
        <x-ui.toast-action :message="$toast" close="hideToast" :href="$toastUrl" link-label="Открыть в пользователях" wire:key="toast-{{ md5($toast . $toastUrl) }}" />
    @endif
</div>
