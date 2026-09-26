<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Настройки" :sub="$tab === 'dictionaries' ? $dictFacts : null">
        @if ($tab === 'dictionaries')
            <x-slot:actions>
                <x-ui.btn variant="dark" icon="plus" wire:click="startAdd">Добавить</x-ui.btn>
            </x-slot:actions>
        @endif
    </x-ui.page-head>

    <div class="flex flex-col gap-6">
        <div class="overflow-x-auto">
            <x-ui.tabs :items="$tabs" model="tab" :active="$tab" />
        </div>

        @if ($tab === 'video')
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                <div class="flex min-w-0 flex-col gap-6">
                    <x-ui.card aria-labelledby="h-srv">
                        <x-ui.card-head id="h-srv" title="Сервер видеосвязи" />
                        <x-ui.field label="Адрес сервера" name="video.bbb_url" type="url" wire:model="video.bbb_url" hint="Косая черта в конце обязательна" />
                        <x-ui.password label="Секретный ключ" name="video.bbb_secret" wire:model="video.bbb_secret" autocomplete="off" />
                    </x-ui.card>
                    <x-ui.card aria-labelledby="h-lim">
                        <x-ui.card-head id="h-lim" title="Ограничения" />
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <x-ui.field label="Максимум участников" name="video.max_participants" type="number" min="0" wire:model="video.max_participants" />
                            <x-ui.field label="Длительность, минут" name="video.duration" type="number" min="0" wire:model="video.duration" />
                        </div>
                        <span class="text-t3 text-muted">0 — без ограничения</span>
                    </x-ui.card>
                </div>
                <x-ui.card aria-labelledby="h-def">
                    <x-ui.card-head id="h-def" title="Занятия по умолчанию" />
                    <x-ui.list>
                        @include('livewire.cabinet.admin.partials.settings-switch', ['group' => 'video', 'key' => 'record', 'title' => 'Записывать занятия', 'on' => $video['record']])
                        @include('livewire.cabinet.admin.partials.settings-switch', ['group' => 'video', 'key' => 'auto_start_recording', 'title' => 'Начинать запись сразу', 'on' => $video['auto_start_recording']])
                        @include('livewire.cabinet.admin.partials.settings-switch', ['group' => 'video', 'key' => 'allow_start_stop_recording', 'title' => 'Учитель может включать и выключать запись', 'on' => $video['allow_start_stop_recording']])
                        @include('livewire.cabinet.admin.partials.settings-switch', ['group' => 'video', 'key' => 'mute_on_start', 'title' => 'Выключать микрофоны при входе', 'on' => $video['mute_on_start']])
                        @include('livewire.cabinet.admin.partials.settings-switch', ['group' => 'video', 'key' => 'webcams_only_for_moderator', 'title' => 'Камеры только у учителя', 'sub' => 'Ученики не видят камеры друг друга', 'on' => $video['webcams_only_for_moderator']])
                    </x-ui.list>
                </x-ui.card>
            </div>
        @elseif ($tab === 'records')
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                <x-ui.card aria-labelledby="h-store">
                    <x-ui.card-head id="h-store" title="Хранение записей" />
                    <x-ui.list>
                        @include('livewire.cabinet.admin.partials.settings-switch', ['group' => 'records', 'key' => 'recording_auto_upload', 'title' => 'Загружать записи в облако', 'sub' => 'Сразу после того, как запись готова', 'on' => $records['recording_auto_upload']])
                        @include('livewire.cabinet.admin.partials.settings-switch', ['group' => 'records', 'key' => 'recording_delete_after_upload', 'title' => 'Удалять с сервера видеосвязи после загрузки', 'sub' => 'Копия останется в облаке', 'on' => $records['recording_delete_after_upload']])
                    </x-ui.list>
                </x-ui.card>
            </div>
        @elseif ($tab === 'payments')
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                <div class="flex min-w-0 flex-col gap-6">
                    {{-- Режим ЮKassa — фокус-блок; переключается сразу, с подтверждением --}}
                    <x-ui.card focus aria-labelledby="h-mode">
                        <x-ui.card-head id="h-mode" title="Режим ЮKassa">
                            @if ($isTest)
                                <x-slot:action><x-ui.badge tone="danger">Тестовый</x-ui.badge></x-slot:action>
                            @endif
                        </x-ui.card-head>
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex min-w-0 flex-col gap-1">
                                <span class="text-t1-s font-medium">Тестовый режим</span>
                                <span class="text-t2 text-muted">{{ $isTest ? 'Деньги не списываются, подходят только тестовые карты' : 'Выключен — платежи идут через боевой магазин' }}</span>
                            </div>
                            <x-ui.switch :checked="$isTest" label="Тестовый режим" wire:click="askMode" />
                        </div>
                        <span class="text-t3 text-muted">Переключается сразу, без «Сохранить»</span>
                    </x-ui.card>
                    <x-ui.card aria-labelledby="h-live">
                        <x-ui.card-head id="h-live" title="Боевой магазин" />
                        <x-ui.field label="Идентификатор магазина" name="payments.yookassa_shop_id" wire:model="payments.yookassa_shop_id" autocomplete="off" />
                        <x-ui.password label="Секретный ключ" name="payments.yookassa_secret_key" wire:model="payments.yookassa_secret_key" autocomplete="off" />
                        <x-ui.list>
                            @include('livewire.cabinet.admin.partials.settings-switch', ['group' => 'payments', 'key' => 'yookassa_recurring_enabled', 'title' => 'Автоплатежи подключены', 'sub' => 'Включайте, когда ЮKassa подключит их магазину', 'on' => $payments['yookassa_recurring_enabled']])
                        </x-ui.list>
                    </x-ui.card>
                </div>
                <div class="flex min-w-0 flex-col gap-6">
                    <x-ui.card aria-labelledby="h-extra">
                        <x-ui.card-head id="h-extra" title="Дополнительные занятия" />
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <x-ui.field label="Цена одного занятия, ₽" name="payments.extra_lesson_price" type="number" min="1" wire:model="payments.extra_lesson_price" />
                            <x-ui.field label="Максимум за одну покупку" name="payments.extra_lessons_max" type="number" min="1" max="100" wire:model="payments.extra_lessons_max" />
                        </div>
                        <span class="text-t3 text-muted">Учитель докупает их, когда занятия по тарифу закончились</span>
                    </x-ui.card>
                    <x-ui.card aria-labelledby="h-test">
                        <x-ui.card-head id="h-test" title="Тестовый магазин" />
                        <x-ui.field label="Идентификатор магазина" name="payments.yookassa_test_shop_id" wire:model="payments.yookassa_test_shop_id" autocomplete="off" />
                        <x-ui.password label="Секретный ключ" name="payments.yookassa_test_secret_key" wire:model="payments.yookassa_test_secret_key" autocomplete="off" />
                    </x-ui.card>
                </div>
            </div>
        @elseif ($tab === 'legal')
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                <x-ui.card aria-labelledby="h-legal">
                    <x-ui.card-head id="h-legal" title="Реквизиты" />
                    <x-ui.field label="Наименование" name="legal.legal_name" wire:model="legal.legal_name" placeholder="ИП Иванов Иван Иванович" />
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <x-ui.field label="ИНН" name="legal.legal_inn" wire:model="legal.legal_inn" inputmode="numeric" />
                        <x-ui.field label="ОГРН или ОГРНИП" name="legal.legal_ogrn" wire:model="legal.legal_ogrn" inputmode="numeric" />
                    </div>
                    <x-ui.field label="Юридический адрес" name="legal.legal_address" wire:model="legal.legal_address" />
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <x-ui.field label="Почта для связи" name="legal.legal_email" type="email" wire:model="legal.legal_email" />
                        <x-ui.field label="Телефон" name="legal.legal_phone" type="tel" wire:model="legal.legal_phone" />
                    </div>
                    <span class="text-t3 text-muted">Видны в подвале сайта и в оферте</span>
                </x-ui.card>
                <x-ui.card aria-labelledby="h-offer">
                    <x-ui.card-head id="h-offer" title="Оферта">
                        <x-slot:action><a href="{{ $offerUrl }}" target="_blank" rel="noopener" class="link text-t2">Открыть оферту</a></x-slot:action>
                    </x-ui.card-head>
                    <x-ui.field label="Дата редакции" name="legal.offer_edition_date" type="date" wire:model="legal.offer_edition_date" hint="Пустая — строки «Редакция от …» на сайте нет" />
                    <x-ui.field label="Платёжный сервис" name="legal.offer_payment_provider" wire:model="legal.offer_payment_provider" />
                    <x-ui.field label="Способы оплаты" name="legal.offer_payment_methods" wire:model="legal.offer_payment_methods" hint="Продолжает фразу «Оплата производится…»" />
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <x-ui.field label="Отказ от услуги, календарных дней" name="legal.offer_refund_days" type="number" min="0" wire:model="legal.offer_refund_days" />
                        <x-ui.field label="Возврат денег, рабочих дней" name="legal.offer_refund_processing_days" type="number" min="0" wire:model="legal.offer_refund_processing_days" />
                    </div>
                </x-ui.card>
            </div>
        @elseif ($tab === 'seo')
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                <div class="flex min-w-0 flex-col gap-6">
                    <x-ui.card aria-labelledby="h-meta">
                        <x-ui.card-head id="h-meta" title="Заголовки и описания" />
                        <x-ui.field label="Название сайта" name="seo.seo_site_name" wire:model="seo.seo_site_name" />
                        <x-ui.field label="Заголовок по умолчанию" name="seo.seo_default_title" wire:model="seo.seo_default_title" hint="Для страниц без своего заголовка, до 60 знаков" />
                        <x-ui.field label="Описание по умолчанию" name="seo.seo_default_description" :rows="2" wire:model="seo.seo_default_description" hint="120–160 знаков" />
                        <x-ui.field label="Заголовок главной страницы" name="seo.seo_home_title" wire:model="seo.seo_home_title" />
                        <x-ui.field label="Описание главной страницы" name="seo.seo_home_description" :rows="3" wire:model="seo.seo_home_description" />
                    </x-ui.card>
                    <x-ui.card aria-labelledby="h-more">
                        <x-ui.card-head id="h-more" title="Дополнительно" />
                        <x-ui.field label="Ссылки на соцсети и каталоги" name="seo.seo_social_links" :rows="2" wire:model="seo.seo_social_links" hint="По одной в строке" />
                        <x-ui.field label="Описание сайта для ИИ-помощников" name="seo.seo_llms_description" :rows="2" wire:model="seo.seo_llms_description" />
                        <x-ui.field label="Код счётчиков и пикселей" name="seo.seo_head_extra" :rows="3" wire:model="seo.seo_head_extra" hint="Вставляется на все страницы сайта как есть" />
                    </x-ui.card>
                </div>
                <div class="flex min-w-0 flex-col gap-6">
                    <x-ui.card aria-labelledby="h-index">
                        <x-ui.card-head id="h-index" title="Поисковики">
                            @unless ($seo['seo_indexing_enabled'])
                                <x-slot:action><x-ui.badge tone="danger">Сайт скрыт</x-ui.badge></x-slot:action>
                            @endunless
                        </x-ui.card-head>
                        <x-ui.list>
                            @include('livewire.cabinet.admin.partials.settings-switch', ['group' => 'seo', 'key' => 'seo_indexing_enabled', 'title' => 'Показывать сайт в поиске', 'sub' => 'Выключайте только на тестовом сайте', 'on' => $seo['seo_indexing_enabled']])
                            @include('livewire.cabinet.admin.partials.settings-switch', ['group' => 'seo', 'key' => 'seo_ai_crawlers_enabled', 'title' => 'Разрешить ИИ-помощникам читать сайт', 'sub' => 'Алиса и другие помощники смогут находить и цитировать сайт', 'on' => $seo['seo_ai_crawlers_enabled']])
                        </x-ui.list>
                        <x-ui.field label="Код подтверждения Яндекс Вебмастера" name="seo.seo_yandex_verification" wire:model="seo.seo_yandex_verification" />
                        <x-ui.field label="Код подтверждения поиска Google" name="seo.seo_google_verification" wire:model="seo.seo_google_verification" />
                    </x-ui.card>
                    <x-ui.card aria-labelledby="h-img">
                        <x-ui.card-head id="h-img" title="Картинки" />
                        <x-ui.list>
                            @foreach ($seoImages as $img)
                                <div wire:key="seo-{{ $img['prop'] }}" class="flex flex-col gap-2 border-t border-line py-4 first:border-t-0 first:pt-0 last:pb-0" x-data>
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-10 w-16 shrink-0 items-center justify-center overflow-hidden rounded-sm bg-soft">
                                            @if ($img['url'])<img src="{{ $img['url'] }}" alt="" class="max-h-full max-w-full object-contain">@else<x-ui.icon name="image" size="s" class="text-muted" />@endif
                                        </span>
                                        <x-ui.text :title="$img['title']" :sub="$img['sub']" />
                                        <x-ui.btn size="s" x-on:click="$refs.file.click()" wire:loading.attr="disabled" wire:target="{{ $img['prop'] }}">{{ $img['action'] }}</x-ui.btn>
                                        <input type="file" x-ref="file" wire:model="{{ $img['prop'] }}" accept="image/png,image/jpeg,image/webp" class="sr-only" tabindex="-1" aria-label="{{ $img['title'] }}">
                                    </div>
                                    @error($img['prop'])<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                                </div>
                            @endforeach
                        </x-ui.list>
                    </x-ui.card>
                </div>
            </div>
        @elseif ($tab === 'b2b')
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                <x-ui.card aria-labelledby="h-b2b">
                    <x-ui.card-head id="h-b2b" title="Блок на странице тарифов">
                        <x-slot:action><a href="{{ $tariffsUrl }}" target="_blank" rel="noopener" class="link text-t2">Как на сайте</a></x-slot:action>
                    </x-ui.card-head>
                    <x-ui.list>
                        @include('livewire.cabinet.admin.partials.settings-switch', ['group' => 'b2b', 'key' => 'b2b_enabled', 'title' => 'Показывать блок', 'sub' => $b2b['b2b_enabled'] ? null : 'Скрыт — на сайте его нет', 'on' => $b2b['b2b_enabled']])
                    </x-ui.list>
                    <x-ui.field label="Заголовок" name="b2b.b2b_title" wire:model="b2b.b2b_title" />
                    <x-ui.field label="Описание" name="b2b.b2b_description" :rows="2" wire:model="b2b.b2b_description" />
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <x-ui.field label="Цена" name="b2b.b2b_price_label" wire:model="b2b.b2b_price_label" hint="«в месяц» добавится само" />
                        <x-ui.field label="Подпись под ценой" name="b2b.b2b_price_note" wire:model="b2b.b2b_price_note" />
                    </div>
                    <x-ui.field label="Почта для кнопки «Написать нам»" name="b2b.b2b_email" type="email" wire:model="b2b.b2b_email" />
                </x-ui.card>
                <x-ui.card aria-labelledby="h-feat">
                    <x-ui.card-head id="h-feat" title="Что входит" />
                    @if (($b2b['b2b_features'] ?? []) !== [])
                        <x-ui.list>
                            @foreach ($b2b['b2b_features'] as $i => $feature)
                                <div wire:key="feat-{{ $i }}-{{ md5($feature) }}" class="flex items-center gap-2 border-t border-line py-2 text-t1-s first:border-t-0 first:pt-0">
                                    <span class="min-w-0 flex-1">{{ $feature }}</span>
                                    <x-ui.btn size="s" square icon="x" wire:click="removeFeature({{ $i }})" aria-label="Убрать пункт" />
                                </div>
                            @endforeach
                        </x-ui.list>
                    @else
                        <span class="text-t2 text-muted">Пока пусто — добавьте, что входит в пакет</span>
                    @endif
                    <div class="flex items-center gap-2">
                        <input type="text" wire:model="featDraft" wire:keydown.enter.prevent="addFeature" placeholder="Новый пункт" aria-label="Новый пункт" class="field min-w-0 flex-1">
                        <x-ui.btn wire:click="addFeature">Добавить</x-ui.btn>
                    </div>
                </x-ui.card>
            </div>
        @elseif ($tab === 'dictionaries')
            @include('livewire.cabinet.admin.partials.settings-dictionaries')
        @endif

        @if ($tab !== 'dictionaries')
            <div class="flex flex-col gap-4 border-t border-line pt-6 lg:flex-row lg:items-center lg:justify-between">
                <span class="text-t2 text-muted">{{ ! empty($dirty[$tab]) ? 'Есть несохранённые изменения на этой вкладке' : '' }}</span>
                <div class="flex items-center gap-4">
                    @if (! empty($saved[$tab]))<x-ui.badge tone="ok">Сохранено</x-ui.badge>@endif
                    <x-ui.btn variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save,ogImage,logo,touchIcon">Сохранить</x-ui.btn>
                </div>
            </div>
        @endif
    </div>

    @if ($modeModal)
        <x-ui.modal :title="$isTest ? 'Включить боевой режим?' : 'Включить тестовый режим?'" sub="ЮKassa" close="closeMode" width="s">
            <p class="text-t1-s">{{ $isTest ? 'Платежи пойдут через боевой магазин с реальным списанием денег.' : 'Платежи пойдут через тестовый магазин: деньги не списываются, подходят только тестовые карты ЮKassa.' }}</p>
            <span class="text-t2 text-muted">Сохранённые карты учителей привязаны к магазину — после переключения они не сработают, пока режим не вернётся.</span>
            <x-slot:footer>
                <x-ui.btn wire:click="closeMode">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="toggleMode">{{ $isTest ? 'Включить боевой режим' : 'Включить тестовый режим' }}</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
