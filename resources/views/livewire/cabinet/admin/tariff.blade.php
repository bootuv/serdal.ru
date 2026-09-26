{{-- Карточка тарифа (макет AdminTariffEdit): форма слева, превью «Так тариф видят учителя» и партнёрская программа справа. --}}
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head :title="$title" :sub="$factLine" :back="route('cabinet.admin.tariffs')" back-label="Тарифы">
        <x-slot:actions>
            @if ($tariffId)
                @if ($isActive)
                    <x-ui.btn wire:click="openHide">Скрыть тариф</x-ui.btn>
                @else
                    <x-ui.btn wire:click="unhide">Вернуть на сайт</x-ui.btn>
                @endif
            @endif
            <x-ui.btn variant="primary" wire:click="save" wire:loading.attr="disabled">Сохранить</x-ui.btn>
        </x-slot:actions>
    </x-ui.page-head>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
            <x-ui.card aria-labelledby="t-main">
                <x-ui.card-head id="t-main" title="Основное" />
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:gap-x-4">
                    <x-ui.field label="Название" name="name" wire:model.live.debounce.400ms="name" />
                    <x-ui.unit-field label="Период" name="periodDays" unit="дней" hint="Сколько дней действует одна оплата" wire:model.live.debounce.400ms="periodDays" />
                    <x-ui.unit-field label="Цена за период" name="price" unit="₽" hint="Новая цена — для следующих оплат и продлений. 0 — бесплатный тариф" wire:model.live.debounce.400ms="price" />
                    <x-ui.unit-field label="Цена за год" name="yearlyPrice" unit="₽" :hint="$yearHint" wire:model.live.debounce.400ms="yearlyPrice" />
                </div>
                <div class="flex flex-col gap-2">
                    <div class="flex items-start justify-between gap-4 rounded-lg p-4 shadow-line">
                        <div class="flex min-w-0 flex-col gap-1">
                            <span class="text-t1-s font-medium">Показывать на сайте</span>
                            <span class="text-t2 text-muted">{{ $siteText }}</span>
                        </div>
                        <x-ui.switch :checked="$isActive" label="Показывать на сайте" wire:click="toggleSite" />
                    </div>
                    <div class="flex items-start justify-between gap-4 rounded-lg p-4 shadow-line">
                        <div class="flex min-w-0 flex-col gap-1">
                            <span class="text-t1-s font-medium">Популярный</span>
                            <span class="text-t2 text-muted">Отметка «Популярный» на странице тарифов</span>
                        </div>
                        <x-ui.switch :checked="$isPopular" label="Популярный" wire:click="$toggle('isPopular')" />
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card aria-labelledby="t-lim">
                <x-ui.card-head id="t-lim" title="Лимиты" />
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:gap-x-4">
                    <x-ui.unit-field label="Занятий в месяц" name="lessons" wire:model.live.debounce.400ms="lessons" :disabled="$lessonsUnlimited">
                        <x-slot:after><x-ui.chip :on="$lessonsUnlimited" wire:click="$toggle('lessonsUnlimited')">Без лимита</x-ui.chip></x-slot:after>
                    </x-ui.unit-field>
                    <x-ui.unit-field label="Участников в занятии" name="participants" hint="Вместе с учителем" wire:model.live.debounce.400ms="participants" />
                    <x-ui.unit-field label="Длительность занятия" name="duration" :unit="$durationUnlimited ? null : 'мин'" wire:model.live.debounce.400ms="duration" :disabled="$durationUnlimited">
                        <x-slot:after><x-ui.chip :on="$durationUnlimited" wire:click="$toggle('durationUnlimited')">Без лимита</x-ui.chip></x-slot:after>
                    </x-ui.unit-field>
                    <x-ui.unit-field label="Хранение записей" name="recording" unit="дней" hint="Пусто — записей на тарифе нет" wire:model.live.debounce.400ms="recording" />
                </div>
            </x-ui.card>

            <x-ui.card aria-labelledby="t-site">
                <x-ui.card-head id="t-site" title="Описание для сайта" />
                <x-ui.field label="Короткое описание" name="shortDescription" wire:model="shortDescription" />
                <x-ui.field label="Подробное описание" name="description" :rows="3" wire:model="description" />
                <x-ui.text-tags label="Что входит" id="t-feat" :items="$features" model="newFeature" add="addFeature" remove="removeFeature" what="пункт" placeholder="Добавить пункт" />
                <x-ui.text-tags label="Дополнительные сервисы" id="t-extra" :items="$extras" model="newExtra" add="addExtra" remove="removeExtra" what="сервис" placeholder="Добавить сервис" hint="На сайте идут под общим списком, через линию" />
            </x-ui.card>
        </div>

        <div class="flex min-w-0 flex-col gap-6">
            <x-ui.card focus aria-labelledby="t-prev">
                <x-ui.card-head id="t-prev" title="Так тариф видят учителя" />
                <div class="flex flex-col gap-4 rounded-lg bg-white p-6">
                    <div class="flex min-h-6 items-center justify-between gap-2">
                        <span class="truncate text-t1 font-semibold">{{ $pvName }}</span>
                        @if ($isPopular)<x-ui.badge>Популярный</x-ui.badge>@endif
                    </div>
                    <div class="flex flex-col gap-1">
                        <span><span class="text-num font-medium">{{ $pvPrice }}</span><span class="text-t2 text-muted">{{ $pvPer }}</span></span>
                        @if ($pvYear)<span class="text-t2 text-muted">{{ $pvYear }}</span>@endif
                    </div>
                    <ul class="flex flex-col gap-2">
                        @foreach ($pvLimits as $line)
                            <li class="flex items-start gap-2 text-t2"><x-ui.icon name="check" size="s" class="mt-1 text-muted" />{{ $line }}</li>
                        @endforeach
                    </ul>
                    @if ($features)
                        <ul class="flex flex-col gap-2 border-t border-line pt-4">
                            @foreach ($features as $line)
                                <li class="flex items-start gap-2 text-t2" wire:key="pv-f-{{ $loop->index }}"><x-ui.icon name="check" size="s" class="mt-1 text-muted" />{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($pvExtras)
                        <span class="border-t border-line pt-4 text-t2 text-muted">{{ $pvExtras }}</span>
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card aria-labelledby="t-ref">
                <x-ui.card-head id="t-ref" title="Партнёрская программа" />
                <x-ui.unit-field label="Бонус пригласившему" name="referralBonus" unit="занятий" :placeholder="$bonusPlaceholder" hint="За первую оплату этого тарифа приглашённым учителем. Пусто — как в настройках программы, 0 — без бонуса" wire:model="referralBonus" />
                <a href="{{ $referralsUrl }}" class="link self-start text-t2">Настройки программы</a>
            </x-ui.card>
        </div>
    </div>

    @if ($hiding && $consequences)
        <x-ui.modal :title="'Скрыть тариф «' . $title . '»?'" :sub="plural_ru($consequences['active'], 'активная подписка', 'активные подписки', 'активных подписок')" close="closeHide" width="s">
            <p class="text-t1-s">Тариф пропадёт с сайта и из выбора тарифа у учителей — подключить или оплатить его будет нельзя.</p>
            @if ($consequences['active'])
                <p class="text-t1-s"><x-ui.em>{{ plural_ru($consequences['active'], 'активная подписка продолжит', 'активные подписки продолжат', 'активных подписок продолжат') }} действовать</x-ui.em> до конца оплаченного срока. История подписок и платежей сохранится.</p>
            @else
                <p class="text-t1-s">История подписок и платежей сохранится.</p>
            @endif
            @if ($freeWarning)<p class="text-t1-s"><x-ui.em>{{ $freeWarning }}</x-ui.em></p>@endif
            <p class="text-t2 text-muted">Вернуть тариф на сайт можно в любой момент.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="closeHide">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="hide">Скрыть тариф</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($undoHide)
        <x-ui.toast-action message="Тариф скрыт с сайта" action="unhide" close="dismissUndo" />
    @endif
</div>
