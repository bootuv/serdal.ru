<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Профиль и цены">
        <x-slot:actions>
            <div class="hidden items-center gap-2 lg:flex">
                <x-ui.btn :href="$subscriptionUrl" icon="wallet">Тариф и платежи</x-ui.btn>
                @if ($tab === 'profile')
                    @if ($saved)<x-ui.badge tone="ok">Сохранено</x-ui.badge>@endif
                    <x-ui.btn variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save,photo">Сохранить</x-ui.btn>
                @endif
            </div>
        </x-slot:actions>
    </x-ui.page-head>

    {{-- Телефон: действия шапки под заголовком --}}
    <div class="flex flex-wrap items-center gap-2 lg:hidden">
        @if ($tab === 'profile')
            <x-ui.btn variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save,photo">Сохранить</x-ui.btn>
        @endif
        <x-ui.btn :href="$subscriptionUrl" icon="wallet">Тариф и платежи</x-ui.btn>
        @if ($tab === 'profile' && $saved)<x-ui.badge tone="ok">Сохранено</x-ui.badge>@endif
    </div>

    <div class="flex flex-col gap-6">
        <x-ui.tabs :items="['profile' => 'Профиль', 'prices' => 'Цены на занятия', 'notify' => 'Уведомления']" model="tab" :active="$tab" />

        @if ($tab === 'profile')
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
                    {{-- Фото и имя --}}
                    <x-ui.card aria-labelledby="p-name">
                        <x-ui.card-head id="p-name" title="Фото и имя" />
                        <div class="flex items-center gap-4" x-data>
                            @if ($photo && ! $errors->has('photo') && $photo->isPreviewable())
                                <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-16 shrink-0 rounded-lg object-cover">
                            @elseif ($user->avatar && ! $removePhoto)
                                <img src="{{ $user->avatar_url }}" alt="" class="size-16 shrink-0 rounded-lg object-cover">
                            @else
                                <x-ui.avatar :name="trim($first_name . ' ' . $last_name) ?: $user->name" :id="$user->id" size="lg" />
                            @endif
                            <div class="flex min-w-0 flex-col gap-2">
                                <div class="flex flex-wrap items-center gap-4">
                                    <x-ui.btn size="s" icon="upload" x-on:click="$refs.photo.click()" wire:loading.attr="disabled" wire:target="photo">Загрузить фото</x-ui.btn>
                                    @if ($photo || ($user->avatar && ! $removePhoto))
                                        <button type="button" class="link text-t2" wire:click="deletePhoto">Удалить</button>
                                    @endif
                                </div>
                                <input type="file" x-ref="photo" wire:model="photo" accept="image/*" class="sr-only" tabindex="-1" aria-label="Фото профиля">
                                <span wire:loading wire:target="photo" class="text-t3 text-muted">Загружаем…</span>
                                @error('photo')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                                <span class="text-t3 text-muted">Квадратное фото, на котором хорошо видно лицо</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                            <x-ui.field label="Фамилия" name="last_name" wire:model.live.debounce.500ms="last_name" autocomplete="family-name" />
                            <x-ui.field label="Имя" name="first_name" wire:model.live.debounce.500ms="first_name" autocomplete="given-name" />
                            <x-ui.field label="Отчество" name="middle_name" wire:model.live.debounce.500ms="middle_name" placeholder="Необязательно" autocomplete="additional-name" />
                        </div>
                    </x-ui.card>

                    {{-- Чему вы учите --}}
                    <x-ui.card aria-labelledby="p-subj">
                        <x-ui.card-head id="p-subj" title="Чему вы учите" />
                        <x-ui.tags label="Предметы" id="f-subj" :selected="$subjects" :options="$subjectOptions" model="addSubject" remove="removeSubject" what="предмет" add="Добавить предмет" />
                        <x-ui.tags label="Направления" id="f-dir" :selected="$directs" :options="$directOptions" model="addDirect" remove="removeDirect" what="направление" />
                        <div class="flex flex-col gap-2">
                            <span id="f-grade" class="text-t2 font-medium">С кем занимаетесь</span>
                            <div class="flex flex-wrap gap-2" role="group" aria-labelledby="f-grade">
                                @foreach ($gradeOptions as $key => $label)
                                    <x-ui.chip :square="is_numeric($key)" :on="in_array((string) $key, $grades, true)" wire:click="toggleGrade('{{ $key }}')" aria-label="{{ $label }}">{{ is_numeric($key) ? $key : $label }}</x-ui.chip>
                                @endforeach
                            </div>
                        </div>
                    </x-ui.card>

                    {{-- О себе и контакты --}}
                    <x-ui.card aria-labelledby="p-about">
                        <x-ui.card-head id="p-about" title="О себе и контакты" />
                        <x-ui.editor label="Обо мне" name="about" wire:model="about" />
                        <x-ui.editor label="Образование и опыт" name="extra_info" wire:model="extra_info" />
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                            <x-ui.field label="Телефон" name="phone" type="tel" wire:model="phone" autocomplete="tel" />
                            <x-ui.field label="WhatsApp" name="whatsup" type="tel" wire:model="whatsup" />
                            <x-ui.field label="Telegram" name="telegram" wire:model="telegram" placeholder="@username" />
                        </div>
                        <span class="text-t3 text-muted">Контакты видны на вашей странице. Любое поле можно оставить пустым</span>
                    </x-ui.card>
                </div>

                <div class="flex min-w-0 flex-col gap-6">
                    {{-- Карточка каталога — как на публичном сайте (partials/specialist-item, телефон) --}}
                    <x-ui.card focus aria-labelledby="p-prev">
                        <x-ui.card-head id="p-prev" title="Так вас видят в каталоге" />
                        <div class="flex gap-4 rounded-lg bg-white px-4 py-6">
                            @if ($photo && ! $errors->has('photo') && $photo->isPreviewable())
                                <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-16 shrink-0 self-start rounded-lg object-cover">
                            @elseif ($user->avatar && ! $removePhoto)
                                <img src="{{ $user->avatar_url }}" alt="" class="size-16 shrink-0 self-start rounded-lg object-cover">
                            @else
                                <x-ui.avatar :name="trim($first_name . ' ' . $last_name) ?: $user->name" :id="$user->id" size="lg" class="self-start" />
                            @endif
                            <div class="flex min-w-0 flex-col gap-3">
                                <div class="flex flex-col items-start gap-3">
                                    <span class="text-h2">{{ $preview['name'] }}</span>
                                    @if ($preview['directs']->isNotEmpty())
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($preview['directs'] as $name)
                                                <span class="rounded-full px-2 text-t2 text-muted shadow-outline">{{ $name }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="flex items-center gap-2 text-t2 text-muted">
                                        @if ($preview['rating'])
                                            <span class="inline-flex items-center gap-1 font-semibold text-ink"><x-ui.icon name="star" size="s" class="fill-star text-star" />{{ $preview['rating'] }}</span>
                                            <span aria-hidden="true">·</span>
                                            <span>{{ plural_ru($preview['reviews'], 'отзыв', 'отзыва', 'отзывов') }}</span>
                                        @else
                                            <span>Пока нет отзывов</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex flex-col items-start gap-3 text-muted">
                                    @if ($preview['price'])
                                        <span class="inline-flex items-baseline gap-2 whitespace-nowrap"><span class="text-h2 font-medium text-ink">{{ $preview['price'] }}</span><span class="text-t2">за занятие</span></span>
                                    @endif
                                    <div class="flex flex-col gap-2">
                                        @if ($preview['subjects'])<span class="text-t1">{{ $preview['subjects'] }}</span>@endif
                                        <span class="text-t2">{{ $preview['grades'] ?: 'Классы не указаны' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if ($preview['url'])
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <a href="{{ $preview['url'] }}" target="_blank" rel="noopener" class="link inline-flex min-w-0 items-center gap-1 text-t2"><span class="truncate">{{ preg_replace('#^https?://#', '', $preview['url']) }}</span><x-ui.icon name="share" size="s" /></a>
                                <x-ui.copy size="s" icon="share" :value="$preview['url']" message="Ссылка на профиль скопирована" />
                            </div>
                        @endif
                    </x-ui.card>

                    {{-- Вход в кабинет --}}
                    <x-ui.card aria-labelledby="p-login">
                        <x-ui.card-head id="p-login" title="Вход в кабинет" />
                        <x-ui.field label="Почта" name="email" type="email" :value="$user->email" disabled class="disabled:bg-soft disabled:text-muted disabled:shadow-none"
                                    hint="Сменить почту можно через поддержку" />
                        <x-ui.field label="Новый пароль" name="password" type="password" wire:model="password" autocomplete="new-password"
                                    placeholder="Оставьте пустым, чтобы не менять" />
                        <form method="POST" action="{{ route('filament.app.auth.logout') }}">
                            @csrf
                            <button type="submit" class="link inline-flex items-center gap-2 text-t2"><x-ui.icon name="logout" size="s" />Выйти из кабинета</button>
                        </form>
                    </x-ui.card>
                </div>
            </div>
        @elseif ($tab === 'prices')
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
                    <x-ui.card focus aria-labelledby="c-types">
                        <x-ui.card-head id="c-types" title="Типы занятий">
                            @if ($canAddPrice)
                                <x-slot:action><x-ui.btn variant="dark" size="s" icon="plus" wire:click="createPrice">Добавить тип занятия</x-ui.btn></x-slot:action>
                            @endif
                        </x-ui.card-head>
                        @if ($prices->isEmpty())
                            <p class="text-t2 text-muted">Пока пусто — добавьте цену, чтобы ученики видели, сколько стоят ваши занятия.</p>
                        @else
                            <x-ui.list>
                                @foreach ($prices as $lt)
                                    @php
                                        $group = $lt->type === \App\Models\LessonType::TYPE_GROUP;
                                        $parts = [];
                                        if ($group && $groupLimit) { $parts[] = 'До ' . $groupLimit->max_participants . ' участников на «' . $groupLimit->name . '»'; }
                                        $parts[] = plural_ru((int) $lt->duration, 'минута', 'минуты', 'минут') . ($lt->isMonthly() && $lt->count_per_week ? ', ' . plural_ru((int) $lt->count_per_week, 'раз', 'раза', 'раз') . ' в неделю' : '');
                                        $perLessonMonthly = $lt->isMonthly() ? $lt->pricePerLesson() : null;
                                    @endphp
                                    <button type="button" wire:click="editPrice({{ $lt->id }})" wire:key="lt-{{ $lt->id }}"
                                            class="group flex w-full items-center gap-4 border-t border-line py-4 text-left text-ink last:pb-0">
                                        <span class="flex size-10 shrink-0 items-center justify-center rounded bg-white"><x-ui.icon :name="$group ? 'users' : 'user'" /></span>
                                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                                            <span class="text-t1 font-medium">{{ $group ? 'Групповые' : 'Индивидуальные' }}</span>
                                            <span class="text-t2 text-muted">
                                                {{ implode(' · ', $parts) }} ·
                                                @if ($lt->isMonthly())
                                                    оплата за месяц@if ($lt->payment_due_day) <x-ui.em>до {{ $lt->payment_due_day }} числа</x-ui.em>@endif
                                                @else
                                                    оплата за занятие@if ($lt->payment_due_days) <x-ui.em>в течение {{ plural_ru((int) $lt->payment_due_days, 'дня', 'дней', 'дней') }}</x-ui.em>@endif
                                                @endif
                                            </span>
                                        </span>
                                        <span class="flex shrink-0 flex-col items-end gap-1 text-right">
                                            <span class="whitespace-nowrap text-t1 font-semibold">{{ number_format((float) $lt->price, 0, ',', ' ') }} ₽</span>
                                            <span class="text-t3 text-muted">{{ $lt->isMonthly() ? 'в месяц' . ($perLessonMonthly ? ' · ≈ ' . number_format($perLessonMonthly, 0, ',', ' ') . ' ₽ за занятие' : '') : 'за занятие' }}</span>
                                        </span>
                                        <x-ui.icon name="chevron-right" class="text-faint group-hover:text-ink" />
                                    </button>
                                @endforeach
                            </x-ui.list>
                        @endif
                    </x-ui.card>
                    <p class="text-t2 text-muted">Эти цены видят ученики. По ним же считаем, сколько и когда ученик должен оплатить.</p>
                </div>

                <x-ui.card aria-labelledby="c-how">
                    <x-ui.card-head id="c-how" title="Если ученик не оплатил" />
                    <p class="text-t2 text-muted">После срока ученик видит напоминание в кабинете. После <x-ui.em>{{ plural_ru($blockAfter, 'неоплаченного занятия', 'неоплаченных занятий', 'неоплаченных занятий') }}</x-ui.em> он не сможет подключаться, пока вы не отметите оплату.</p>
                </x-ui.card>
            </div>
        @else
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
                    <x-ui.card focus>
                        <livewire:push-notification-toggle variant="cabinet" key="push-switch" />
                        <span class="text-t3 text-muted">Не приходят? Разрешите уведомления для сайта в настройках браузера — на телефоне и компьютере отдельно</span>
                    </x-ui.card>

                    <x-ui.card aria-labelledby="n-what">
                        <x-ui.card-head id="n-what" title="О чём сообщаем" />
                        <x-ui.list>
                            @foreach ([
                                ['Новое сообщение', 'От ученика или поддержки'],
                                ['Работа сдана', 'Ученик прислал задание на проверку'],
                                ['Новый ученик', 'Ученик принял ваше приглашение'],
                                ['Новый отзыв', 'Ученик оставил отзыв на вашей странице'],
                                ['Тариф', 'Скоро закончится, продлён или не получилось списать оплату'],
                            ] as [$title, $sub])
                                <x-ui.row><x-ui.text :title="$title" :sub="$sub" /></x-ui.row>
                            @endforeach
                        </x-ui.list>
                        <span class="text-t3 text-muted">То же появляется в кабинете. Выбрать отдельные события пока нельзя</span>
                    </x-ui.card>
                </div>
            </div>
        @endif
    </div>

    {{-- Окно цены --}}
    @if ($priceOpen)
        @php $monthly = $pricePayment === \App\Models\LessonType::PAYMENT_MONTHLY; @endphp
        <x-ui.modal :title="$priceId ? 'Изменить цену' : 'Новая цена'" sub="Её увидят ученики на вашей странице" close="closePrice">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-ui.select label="Тип занятий" name="priceType" :options="collect($priceTypes)->map(fn ($l, $k) => $k === \App\Models\LessonType::TYPE_GROUP ? 'Групповые' : 'Индивидуальные')->all()" wire:model.live="priceType" />
                <div class="flex flex-col gap-2">
                    <span class="text-t2 font-medium">Как платят ученики</span>
                    <x-ui.seg :items="['per_lesson' => 'За занятие', 'monthly' => 'За месяц']" model="pricePayment" :active="$pricePayment" />
                </div>
                <x-ui.field :label="$monthly ? 'Цена за месяц, ₽' : 'Цена за занятие, ₽'" name="price" type="number" min="0" inputmode="numeric" wire:model="price" />
                <x-ui.field label="Длительность, мин" name="priceDuration" type="number" min="1" inputmode="numeric" wire:model="priceDuration" />
                @if ($monthly)
                    <x-ui.field label="Занятий в неделю" name="priceCount" type="number" min="1" inputmode="numeric" wire:model="priceCount" />
                    <x-ui.field label="Оплата до числа месяца" name="priceDueDay" type="number" min="1" max="28" inputmode="numeric" wire:model="priceDueDay"
                                hint="До какого числа каждого месяца ученик вносит оплату" />
                @else
                    <x-ui.field label="Срок оплаты после занятия, дней" name="priceDueDays" type="number" min="1" max="30" inputmode="numeric" wire:model="priceDueDays"
                                hint="Через сколько дней после занятия ученик должен оплатить" />
                @endif
            </div>
            <x-slot:note>
                @if ($priceId)<button type="button" class="link" wire:click="confirmDeletePrice">Удалить цену</button>@endif
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="closePrice">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="savePrice" wire:loading.attr="disabled" wire:target="savePrice">Сохранить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($deletePriceId)
        <x-ui.modal title="Удалить цену?" sub="Её можно будет добавить заново" close="$set('deletePriceId', null)" width="s">
            <p class="text-t1">Ученики перестанут видеть эту цену на вашей странице.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="$set('deletePriceId', null)">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="deletePrice">Удалить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
