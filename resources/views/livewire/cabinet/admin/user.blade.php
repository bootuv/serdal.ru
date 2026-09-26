@php
    $role = $u->role;
    $teacher = $role === \App\Models\User::ROLE_TUTOR;
    $student = $role === \App\Models\User::ROLE_STUDENT;
    $first = $u->first_name ?: $u->name;
    $who = $teacher ? 'учителя' : ($student ? 'ученика' : 'администратора');
    $facts = match (true) {
        $teacher => array_filter([$u->email, $u->phone, $since]),
        $student => [...$facts, $since],
        default => array_filter([$u->email, 'последний вход ' . $seen, $since]),
    };
    $dl = 'flex items-baseline justify-between gap-4 border-t border-line py-3 text-t1-s first:border-t-0 first:pt-0 last:pb-0';
@endphp
<div class="flex flex-col gap-6 lg:gap-8">
    {{-- Шапка: назад, аватар 64, имя и отметки, одна строка фактов; справа действия --}}
    <div class="flex flex-col gap-4">
        <a href="{{ $backUrl }}" class="inline-flex items-center gap-2 self-start text-t1-s font-medium text-muted hover:text-ink"><x-ui.icon name="arrow-left" size="s" />Пользователи</a>
        <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between lg:gap-6">
            <div class="flex min-w-0 items-center gap-4">
                @if ($u->avatar)
                    <img src="{{ $u->avatar_url }}" alt="" class="size-16 shrink-0 rounded-lg object-cover">
                @else
                    <x-ui.avatar :user="$u" size="lg" />
                @endif
                <div class="flex min-w-0 flex-col gap-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-h1-m font-medium lg:text-h1">{{ $u->name }}</h1>
                        @if ($u->is_blocked)
                            <x-ui.badge tone="danger">Заблокирован</x-ui.badge>
                        @elseif ($teacher && ! $u->is_active)
                            <x-ui.badge>Скрыт из каталога</x-ui.badge>
                        @endif
                    </div>
                    <p class="text-t1 text-muted">{{ implode(' · ', $facts) }}</p>
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                @if ($isSelf)
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-ui.btn type="submit" icon="logout">Выйти</x-ui.btn>
                    </form>
                @endif
                @unless ($isSelf)
                    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                        <x-ui.btn square icon="more" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="menu" aria-label="Другие действия" />
                        <div x-show="open" x-cloak x-on:click="open = false" role="menu"
                             class="absolute left-0 top-full z-10 mt-1 flex w-44 flex-col rounded border border-line bg-white p-1 shadow-card lg:left-auto lg:right-0">
                            @if ($teacher)
                                <x-ui.menu-item wire:click="toggleHidden">{{ $u->is_active ? 'Скрыть из каталога' : 'Вернуть в каталог' }}</x-ui.menu-item>
                            @endif
                            @if ($u->is_blocked)
                                <x-ui.menu-item wire:click="unblock">Разблокировать</x-ui.menu-item>
                            @else
                                <x-ui.menu-item wire:click="openBlock">Заблокировать</x-ui.menu-item>
                            @endif
                            <span class="my-1 border-t border-line" aria-hidden="true"></span>
                            <x-ui.menu-item wire:click="openDelete">Удалить</x-ui.menu-item>
                        </div>
                    </div>
                @endunless
                @if ($teacher && $catalogUrl && $u->is_active && ! $u->is_blocked)
                    <x-ui.btn icon="share" :href="$catalogUrl" target="_blank" rel="noopener">Открыть страницу в каталоге</x-ui.btn>
                @endif
                @if ($teacher || $student)
                    <x-ui.btn icon="chat" wire:click="write">Написать</x-ui.btn>
                @endif
            </div>
        </header>
    </div>

    <div class="flex flex-col gap-6">
        @if ($teacher || $student)
            <x-ui.tabs :items="$tabItems" model="tab" :active="$tab" :aria-label="$teacher ? 'Разделы карточки учителя' : 'Разделы карточки ученика'" />
        @endif

        @if ($teacher && $tab === 'overview')
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
                    {{-- Фокус: тариф и лимит --}}
                    <x-ui.card focus aria-labelledby="o-tar">
                        @if ($tar)
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex min-w-0 flex-col gap-1">
                                    <h2 id="o-tar" class="text-h2 font-medium">Тариф «{{ $tar['name'] }}»</h2>
                                    <span class="text-t2 text-muted">{{ $tar['term'] }}</span>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1 text-right">
                                    <span class="text-t1 font-semibold">{{ $tar['price'] }}</span>
                                    @if ($tar['per'])<span class="text-t3 text-muted">{{ $tar['per'] }}</span>@endif
                                </div>
                            </div>
                            <div class="flex flex-col gap-2">
                                <div class="flex items-baseline justify-between gap-4 text-t2">
                                    <span class="font-medium">Занятия в этом периоде</span>
                                    @if ($tar['limit'] !== null)
                                        <span><x-ui.em>{{ $tar['used'] }}</x-ui.em> из {{ $tar['limit'] }}</span>
                                    @else
                                        <span><x-ui.em>{{ $tar['used'] }}</x-ui.em> · без лимита</span>
                                    @endif
                                </div>
                                @if ($tar['limit'] !== null)
                                    <x-ui.progress on-mint :value="$tar['used']" :max="$tar['limit']" label="Занятия в этом периоде" />
                                @endif
                                @if ($tar['resets'])<span class="text-t3 text-muted">Лимит обновится {{ $tar['resets'] }}</span>@endif
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                @foreach (['Участников в занятии' => $tar['people'], 'Длительность занятия' => $tar['length'], 'Записи занятий' => $tar['rec'], 'Продление' => $tar['renew']] as $label => $value)
                                    <div class="flex min-w-0 flex-col gap-1"><span class="text-t2 text-muted">{{ $label }}</span><span class="text-t1-s font-medium">{{ $value }}</span></div>
                                @endforeach
                            </div>
                            @if ($tar['note'])<span class="text-t2 text-muted">{{ $tar['note'] }}</span>@endif
                        @else
                            <div class="flex flex-col gap-1">
                                <h2 id="o-tar" class="text-h2 font-medium">Тарифа нет</h2>
                                <span class="text-t2 text-muted">{{ $noTariffNote }} — занятия проводить нельзя</span>
                            </div>
                        @endif
                        <x-ui.btn variant="primary" class="self-start" wire:click="openTariff">Назначить тариф</x-ui.btn>
                    </x-ui.card>

                    <x-ui.card aria-labelledby="o-pays">
                        <x-ui.card-head id="o-pays" title="Платежи">
                            @if ($paymentsUrl && $payments->isNotEmpty())
                                <x-slot:action><a href="{{ $paymentsUrl }}" class="link text-t2">Все платежи</a></x-slot:action>
                            @endif
                        </x-ui.card-head>
                        @if ($payments->isEmpty())
                            <p class="text-t2 text-muted">Пока пусто — учитель ещё не платил за тариф.</p>
                        @else
                            <x-ui.list>
                                @foreach ($payments as $p)
                                    <x-ui.row wire:key="pay-{{ $p['id'] }}">
                                        <x-ui.text :title="$p['title']" :sub="$p['meta']" />
                                        @if ($p['badge'])<x-ui.badge :tone="$p['badge'][1]">{{ $p['badge'][0] }}</x-ui.badge>@endif
                                        <span @class(['w-24 shrink-0 whitespace-nowrap text-right text-t1-s font-medium', 'text-muted' => $p['muted']])>{{ $p['amount'] }}</span>
                                    </x-ui.row>
                                @endforeach
                            </x-ui.list>
                        @endif
                    </x-ui.card>
                </div>

                <div class="flex min-w-0 flex-col gap-6">
                    <x-ui.card aria-labelledby="o-extra">
                        <x-ui.card-head id="o-extra" title="Докупленные занятия" />
                        @if ($extraBalance === 0 && $extras->isEmpty())
                            <p class="text-t2 text-muted">Пока пусто — учитель не докупал занятия.</p>
                        @else
                            <div class="flex flex-col gap-1">
                                <span class="text-num font-medium">{{ plural_ru($extraBalance, 'занятие', 'занятия', 'занятий') }}</span>
                                <span class="text-t2 text-muted">Не сгорают. Тратятся, когда закончится лимит тарифа</span>
                            </div>
                            @if ($extras->isNotEmpty())
                                <x-ui.list>
                                    @foreach ($extras as $e)
                                        <x-ui.row>
                                            <div class="flex min-w-0 flex-1 flex-col gap-1"><span class="text-t1-s font-medium">{{ $e['title'] }}</span><span class="text-t2 text-muted">{{ $e['meta'] }}</span></div>
                                            <span class="shrink-0 whitespace-nowrap text-t1-s font-medium">{{ $e['amount'] }}</span>
                                        </x-ui.row>
                                    @endforeach
                                </x-ui.list>
                            @endif
                        @endif
                    </x-ui.card>

                    <x-ui.card aria-labelledby="o-act">
                        <x-ui.card-head id="o-act" title="Активность" />
                        <div class="flex flex-col">
                            @foreach ($activity as [$label, $value, $href])
                                <div class="{{ $dl }}"><span class="whitespace-nowrap text-t2 text-muted">{{ $label }}</span>@if ($href)<a href="{{ $href }}" class="link text-right font-medium">{{ $value }}</a>@else<span class="text-right font-medium">{{ $value }}</span>@endif</div>
                            @endforeach
                        </div>
                    </x-ui.card>
                </div>
            </div>
        @elseif ($teacher && $tab === 'profile')
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
                    <x-ui.card aria-labelledby="p-subj">
                        <x-ui.card-head id="p-subj" title="Чему учит" />
                        <x-ui.tags label="Предметы" id="f-subj" :selected="$subjects" :options="$subjectOptions" model="addSubject" remove="removeSubject" what="предмет" add="Добавить предмет" />
                        <x-ui.tags label="Направления" id="f-dir" :selected="$directs" :options="$directOptions" model="addDirect" remove="removeDirect" what="направление" add="Добавить направление" />
                        <div class="flex flex-col gap-2">
                            <span id="f-grade" class="text-t2 font-medium">Классы</span>
                            <div class="flex flex-wrap gap-2" role="group" aria-labelledby="f-grade">
                                @foreach ($gradeOptions as $key => $label)
                                    <x-ui.chip :square="is_numeric($key)" :on="in_array((string) $key, $grades, true)" wire:click="toggleGrade('{{ $key }}')" aria-label="{{ $label }}">{{ is_numeric($key) ? $key : $label }}</x-ui.chip>
                                @endforeach
                            </div>
                        </div>
                    </x-ui.card>

                    <x-ui.card aria-labelledby="p-about">
                        <x-ui.card-head id="p-about" title="О себе и опыт" />
                        <x-ui.editor label="О себе" name="about" wire:model="about" />
                        <x-ui.editor label="Образование и опыт" name="extra_info" wire:model="extra_info" />
                    </x-ui.card>

                    <div class="flex flex-wrap items-center gap-4">
                        <x-ui.btn variant="primary" wire:click="saveProfile" wire:loading.attr="disabled" wire:target="saveProfile,photo">Сохранить</x-ui.btn>
                        <span class="text-t2 text-muted">Изменения сразу появятся на странице в каталоге</span>
                    </div>
                </div>

                <div class="flex min-w-0 flex-col gap-6">
                    <x-ui.card aria-labelledby="p-name">
                        <x-ui.card-head id="p-name" title="Фото и имя" />
                        <div class="flex items-center gap-4" x-data>
                            @if ($photo && ! $errors->has('photo') && $photo->isPreviewable())
                                <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-16 shrink-0 rounded-lg object-cover">
                            @elseif ($u->avatar && ! $removePhoto)
                                <img src="{{ $u->avatar_url }}" alt="" class="size-16 shrink-0 rounded-lg object-cover">
                            @else
                                <x-ui.avatar :user="$u" size="lg" />
                            @endif
                            <div class="flex min-w-0 flex-col items-start gap-2">
                                <x-ui.btn size="s" icon="upload" x-on:click="$refs.photo.click()" wire:loading.attr="disabled" wire:target="photo">Загрузить фото</x-ui.btn>
                                @if ($photo || ($u->avatar && ! $removePhoto))
                                    <button type="button" class="link text-t2" wire:click="deletePhoto">Удалить фото</button>
                                @endif
                                <input type="file" x-ref="photo" wire:model="photo" accept="image/*" class="sr-only" tabindex="-1" aria-label="Фото профиля">
                                @error('photo')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <x-ui.field label="Фамилия" name="last_name" wire:model="last_name" />
                        <x-ui.field label="Имя" name="first_name" wire:model="first_name" />
                        <x-ui.field label="Отчество" name="middle_name" wire:model="middle_name" optional />
                    </x-ui.card>

                    <x-ui.card aria-labelledby="p-contacts">
                        <x-ui.card-head id="p-contacts" title="Вход и контакты" />
                        <x-ui.field label="Почта для входа" name="email" type="email" wire:model="email" />
                        <x-ui.field label="Телефон" name="phone" type="tel" wire:model="phone" />
                        <x-ui.field label="WhatsApp" name="whatsup" type="tel" wire:model="whatsup" />
                        <x-ui.field label="Telegram" name="telegram" wire:model="telegram" placeholder="@ник" />
                        <div class="flex flex-col items-start gap-2 border-t border-line pt-4">
                            <span class="text-t2 font-medium">Пароль</span>
                            <x-ui.btn size="s" wire:click="sendReset" wire:loading.attr="disabled" wire:target="sendReset">Отправить ссылку для смены пароля</x-ui.btn>
                        </div>
                    </x-ui.card>
                </div>
            </div>
        @elseif ($teacher && $tab === 'prices')
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                <x-ui.card class="lg:col-span-2" aria-labelledby="c-types">
                    <x-ui.card-head id="c-types" title="Типы занятий" />
                    @if ($prices->isEmpty())
                        <p class="text-t2 text-muted">Пока пусто — учитель ещё не указал цены.</p>
                    @else
                        <x-ui.list>
                            @foreach ($prices as $lt)
                                @php $group = $lt->type === \App\Models\LessonType::TYPE_GROUP; @endphp
                                <button type="button" wire:click="editPrice({{ $lt->id }})" wire:key="lt-{{ $lt->id }}"
                                        class="group flex w-full items-center gap-4 border-t border-line py-4 text-left text-ink last:pb-0">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded bg-soft"><x-ui.icon :name="$group ? 'users' : 'user'" /></span>
                                    <span class="flex min-w-0 flex-1 flex-col gap-1">
                                        <span class="text-t1 font-medium">{{ $group ? 'Групповые занятия' : 'Индивидуальные занятия' }}</span>
                                        <span class="text-t2 text-muted">{{ plural_ru((int) $lt->duration, 'минута', 'минуты', 'минут') }}{{ $lt->isMonthly() && $lt->count_per_week ? ', ' . plural_ru((int) $lt->count_per_week, 'раз', 'раза', 'раз') . ' в неделю' : '' }}</span>
                                    </span>
                                    <span class="flex shrink-0 flex-col items-end gap-1 text-right">
                                        <span class="whitespace-nowrap text-t1 font-semibold">{{ \App\Support\Money::format((int) $lt->price) }}</span>
                                        <span class="text-t3 text-muted">{{ $lt->isMonthly() ? 'в месяц' : 'за занятие' }}</span>
                                    </span>
                                    <x-ui.icon name="chevron-right" class="text-faint group-hover:text-ink" />
                                </button>
                            @endforeach
                        </x-ui.list>
                    @endif
                    <span class="text-t2 text-muted">Эти цены видят ученики на странице учителя в каталоге</span>
                </x-ui.card>
            </div>
        @elseif ($teacher && $tab === 'people')
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                <x-ui.card class="lg:col-span-2" aria-labelledby="s-list">
                    <x-ui.card-head id="s-list" title="Ученики">
                        @if ($peopleSub)
                            <x-slot:action><span class="text-t2 text-muted">{{ $peopleSub }}</span></x-slot:action>
                        @endif
                    </x-ui.card-head>
                    @if ($students->isEmpty())
                        <p class="text-t2 text-muted">Пока пусто — учеников у учителя нет.</p>
                    @else
                        <x-ui.list>
                            @foreach ($students as $s)
                                <x-ui.row :href="route('cabinet.admin.user', ['user' => $s['user']->id])" wire:key="st-{{ $s['user']->id }}">
                                    <x-ui.avatar :user="$s['user']" />
                                    <x-ui.text :title="$s['user']->name" :sub="$s['sub']" />
                                </x-ui.row>
                            @endforeach
                        </x-ui.list>
                    @endif
                </x-ui.card>
                <div class="flex min-w-0 flex-col gap-6">
                    <x-ui.card aria-labelledby="s-next">
                        <x-ui.card-head id="s-next" title="Ближайшие">
                            @if ($lessonsUrl)
                                <x-slot:action><a href="{{ $lessonsUrl }}" class="link text-t2">Все занятия</a></x-slot:action>
                            @endif
                        </x-ui.card-head>
                        @if ($next->isEmpty())
                            <p class="text-t2 text-muted">Пока пусто — ближайших занятий нет.</p>
                        @else
                            <x-ui.list>
                                @foreach ($next as $n)
                                    <x-ui.row :href="$n['href']" :chevron="(bool) $n['href']">
                                        <x-ui.text :title="$n['when']" :sub="$n['sub']" />
                                    </x-ui.row>
                                @endforeach
                            </x-ui.list>
                        @endif
                    </x-ui.card>
                    <x-ui.card aria-labelledby="s-past">
                        <x-ui.card-head id="s-past" title="Проведённые">
                            @if ($sessionsUrl)
                                <x-slot:action><a href="{{ $sessionsUrl }}" class="link text-t2">Все проведённые</a></x-slot:action>
                            @endif
                        </x-ui.card-head>
                        <div class="flex flex-col">
                            @foreach ($past as [$label, $value])
                                <div class="{{ $dl }}"><span class="whitespace-nowrap text-t2 text-muted">{{ $label }}</span><span class="text-right font-medium">{{ $value }}</span></div>
                            @endforeach
                        </div>
                    </x-ui.card>
                </div>
            </div>
        @elseif ($student && $tab === 'overview')
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                {{-- Фокус: учителя и занятия --}}
                <x-ui.card focus class="lg:col-span-2" aria-labelledby="o-lessons">
                    <x-ui.card-head id="o-lessons" title="Учителя и занятия">
                        @if ($teachers->isNotEmpty())
                            <x-slot:action><x-ui.seg fit :items="['next' => 'Ближайшие', 'past' => 'Прошедшие']" model="view" :active="$view" aria-label="Какие занятия показать" /></x-slot:action>
                        @endif
                    </x-ui.card-head>
                    @if ($groups->isEmpty())
                        <p class="text-t2 text-muted">Пока нет учителя — ученик появится в списке учителя, когда тот добавит его.</p>
                    @else
                        @foreach ($groups as $g)
                            <div @class(['flex flex-col gap-4', 'border-t border-line pt-6' => ! $loop->first]) wire:key="grp-{{ $g['teacher']->id }}">
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :user="$g['teacher']" />
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <a href="{{ route('cabinet.admin.user', ['user' => $g['teacher']->id]) }}" class="truncate text-t1 font-medium hover:underline">{{ $g['teacher']->name }}</a>
                                        <span class="text-t2 text-muted">{{ $g['terms'] }}</span>
                                    </div>
                                </div>
                                @if ($g['rows']->isEmpty())
                                    <p class="text-t2 text-muted">{{ $g['empty'] }}</p>
                                @else
                                    <x-ui.list>
                                        @foreach ($g['rows'] as $r)
                                            <x-ui.row :href="$r['href']" :chevron="(bool) $r['href']">
                                                <div class="flex min-w-0 flex-1 flex-col gap-1"><span class="text-t1-s font-medium">{{ $r['title'] }}</span><span class="truncate text-t2 text-muted">{{ $r['sub'] }}</span></div>
                                                @if ($r['badge'])<x-ui.badge :tone="$r['badge'][1]">{{ $r['badge'][0] }}</x-ui.badge>@endif
                                            </x-ui.row>
                                        @endforeach
                                    </x-ui.list>
                                @endif
                            </div>
                        @endforeach
                        @if ($lessonsUrl)<a href="{{ $lessonsUrl }}" class="link self-start text-t2">Все занятия</a>@endif
                    @endif
                </x-ui.card>

                <div class="flex min-w-0 flex-col gap-6">
                    @foreach (['o-act' => ['Активность', $activity], 'o-contacts' => ['Контакты', $contacts]] as $id => [$title, $items])
                        <x-ui.card :aria-labelledby="$id">
                            <x-ui.card-head :id="$id" :title="$title" />
                            <div class="flex flex-col">
                                @foreach ($items as [$label, $value, $href])
                                    <div class="{{ $dl }}"><span class="whitespace-nowrap text-t2 text-muted">{{ $label }}</span>@if ($href)<a href="{{ $href }}" class="link min-w-0 truncate text-right font-medium">{{ $value }}</a>@else<span class="min-w-0 truncate text-right font-medium">{{ $value }}</span>@endif</div>
                                @endforeach
                            </div>
                        </x-ui.card>
                    @endforeach
                </div>
            </div>
        @else
            {{-- Профиль ученика и администратора --}}
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
                    <x-ui.card aria-labelledby="p-main">
                        <x-ui.card-head id="p-main" title="Профиль" />
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                            <x-ui.field label="Фамилия" name="last_name" wire:model="last_name" />
                            <x-ui.field label="Имя" name="first_name" wire:model="first_name" />
                            <x-ui.field label="Отчество" name="middle_name" wire:model="middle_name" optional />
                        </div>
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <x-ui.field label="Почта для входа" name="email" type="email" wire:model="email" />
                            <x-ui.field label="Телефон" name="phone" type="tel" wire:model="phone" />
                        </div>
                        @if ($student)
                            <div class="flex flex-col gap-2">
                                <span id="f-grade" class="text-t2 font-medium">Класс</span>
                                <div class="flex flex-wrap gap-2" role="radiogroup" aria-labelledby="f-grade">
                                    @foreach ($gradeOptions as $key => $label)
                                        <x-ui.chip :square="is_numeric($key)" :on="(string) $key === $grade" wire:click="$set('grade', '{{ (string) $key === $grade ? '' : $key }}')" aria-label="{{ $label }}">{{ is_numeric($key) ? $key : $label }}</x-ui.chip>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </x-ui.card>
                    <div class="flex flex-wrap items-center gap-4">
                        <x-ui.btn variant="primary" wire:click="saveProfile" wire:loading.attr="disabled" wire:target="saveProfile">Сохранить</x-ui.btn>
                        @if ($student)<span class="text-t2 text-muted">{{ $first }} увидит изменения в своём профиле</span>@endif
                    </div>
                </div>
                <x-ui.card aria-labelledby="p-pass">
                    <x-ui.card-head id="p-pass" title="Пароль" />
                    <span class="text-t2 text-muted">{{ $isSelf ? 'Пришлём вам на почту ссылку, по которой вы зададите новый пароль' : 'Пришлём на почту ссылку, по которой ' . $first . ' задаст новый пароль' }}</span>
                    <x-ui.btn size="s" class="self-start" wire:click="sendReset" wire:loading.attr="disabled" wire:target="sendReset">Отправить ссылку</x-ui.btn>
                </x-ui.card>
            </div>
        @endif
    </div>

    {{-- Окно «Назначить тариф» (макет AdminUserTeacherTariff) --}}
    @if ($modal === 'tariff' && $teacher)
        @php
            $picked = $tariffs->firstWhere('id', (int) $tariffId);
            $pickedFree = $picked?->isFree();
            $termDays = ['month' => 30, 'q' => 90, 'year' => 365, 'custom' => (int) $customDays][$term] ?? null;
            $current = $current ?? null;
        @endphp
        <x-ui.modal title="Назначить тариф" :sub="$u->name . ' · ' . ($current ? 'сейчас «' . $current->tariff->name . '»' . ($current->ends_at ? ' до ' . \App\Support\HumanDate::date($current->ends_at) : '') : 'сейчас без тарифа')" close="closeModal">
            <div class="flex flex-col gap-2" role="radiogroup" aria-labelledby="tr-tariff">
                <span id="tr-tariff" class="text-t2 font-medium">Тариф</span>
                @foreach ($tariffs as $t)
                    <x-ui.option type="radio" name="tariffId" value="{{ $t->id }}" wire:model.live="tariffId" wire:key="tr-{{ $t->id }}">
                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="truncate text-t1-s font-medium">{{ $t->name }}</span>
                            <span class="text-t2 text-muted">{{ $t->isFree() ? 'Бесплатно' : \App\Support\Money::format((int) $t->price) . ' в месяц' }} · {{ $t->lessons_per_month ? plural_ru($t->lessons_per_month, 'занятие', 'занятия', 'занятий') . ' в месяц' : 'занятия без лимита' }}{{ $t->max_participants ? ' · до ' . plural_ru($t->max_participants, 'участника', 'участников', 'участников') : '' }}</span>
                        </span>
                        @if ($current && $current->tariff_id === $t->id)<x-ui.badge>Сейчас</x-ui.badge>@endif
                    </x-ui.option>
                @endforeach
                @error('tariffId')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            </div>
            @if ($picked && $pickedFree)
                <p class="text-t2 text-muted">«{{ $picked->name }}» бесплатный и действует бессрочно.</p>
            @elseif ($picked)
                <div class="flex flex-col gap-2">
                    <span class="text-t2 font-medium">Срок</span>
                    <x-ui.seg :items="['month' => 'Месяц', 'q' => '3 месяца', 'year' => 'Год', 'custom' => 'Другой срок', 'forever' => 'Бессрочно']" model="term" :active="$term" aria-label="Срок" />
                    @if ($term === 'custom')
                        <div class="flex items-center gap-3">
                            <input type="number" min="1" wire:model.live.debounce.400ms="customDays" aria-label="Срок в днях" class="field w-40">
                            <span class="text-t2 text-muted">{{ $termDays > 0 ? 'дней · действует до ' . \App\Support\HumanDate::day(now()->addDays($termDays)) : 'дней' }}</span>
                        </div>
                        @error('customDays')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                    @elseif ($term === 'forever')
                        <span class="text-t2 text-muted">Без даты окончания — например, тариф навсегда</span>
                    @else
                        <span class="text-t2 text-muted">Действует до {{ \App\Support\HumanDate::day(now()->addDays($termDays)) }}</span>
                    @endif
                </div>
                <div class="flex items-center justify-between gap-4">
                    <div class="flex min-w-0 flex-col gap-1">
                        <span id="tr-free" class="text-t1-s font-medium">Без оплаты</span>
                        <span class="text-t2 text-muted">Учитель увидит «Предоставлен бесплатно» — без цены и кнопки оплаты</span>
                    </div>
                    <x-ui.switch :checked="$free" label="Без оплаты" wire:click="$toggle('free')" />
                </div>
            @endif
            <x-ui.field label="Комментарий" name="note" wire:model="note" optional placeholder="Например: за помощь с тестированием" hint="Виден только администраторам" />
            <p class="text-t2 text-muted">{{ $current ? 'Текущая подписка «' . $current->tariff->name . '» будет заменена. ' : '' }}{{ $first }} получит уведомление о новом тарифе.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="closeModal">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="assignTariff" wire:loading.attr="disabled" wire:target="assignTariff">Назначить тариф</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Заблокировать --}}
    @if ($modal === 'block')
        <x-ui.modal :title="'Заблокировать ' . $who . '?'" :sub="$u->name . ($teacher ? ' · ' . ($studentsCount ? plural_ru($studentsCount, 'ученик', 'ученика', 'учеников') : 'учеников нет') : ($student ? ' · ' . ($teachers->isNotEmpty() ? plural_ru($teachers->count(), 'учитель', 'учителя', 'учителей') : 'без учителя') : ''))" close="closeModal" width="s">
            <p class="text-t1">
                @if ($teacher)
                    {{ $first }} не сможет войти в кабинет и начать занятия{{ $todayLessons ? ', включая сегодняшние в ' . implode(' и ', $todayLessons) : '' }}. Страница пропадёт из каталога.
                @elseif ($student)
                    {{ $first }} не сможет войти в кабинет и подключиться к занятиям{{ $teachers->isNotEmpty() ? ' учителей: ' . $teachers->pluck('name')->implode(', ') : '' }}. Задания и записи занятий сохранятся.
                @else
                    {{ $first }} не сможет войти в админку.
                @endif
            </p>
            <span class="text-t2 text-muted">Разблокировать можно в любой момент</span>
            <x-slot:footer>
                <x-ui.btn wire:click="closeModal">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="block" wire:loading.attr="disabled" wire:target="block">Заблокировать</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Удалить --}}
    @if ($modal === 'delete')
        <x-ui.modal :title="'Удалить ' . $who . '?'" :sub="$u->name . ' · ' . $since" close="closeModal" width="s">
            <p class="text-t1">
                @if ($teacher)
                    Удалятся профиль, страница в каталоге, занятия с расписанием и записями, задания и материалы.
                    @if ($impact['students'] ?? 0){{ plural_ru($impact['students'], 'ученик больше не увидит', 'ученика больше не увидят', 'учеников больше не увидят') }} эти занятия.@endif
                @elseif ($student)
                    Удалятся профиль и сданные задания.@if (! empty($impact['teachers'])) Ученик пропадёт из списков: {{ implode(', ', $impact['teachers']) }}.@endif
                @else
                    Удалится профиль администратора.
                @endif
                <x-ui.em>Отменить нельзя.</x-ui.em>
            </p>
            <span class="text-t2 text-muted">Если нужно только закрыть доступ — заблокируйте</span>
            <x-slot:footer>
                <x-ui.btn wire:click="closeModal">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">Удалить навсегда</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Цена типа занятий --}}
    @if ($modal === 'price' && $teacher)
        @php $monthly = $pricePayment === \App\Models\LessonType::PAYMENT_MONTHLY; @endphp
        <x-ui.modal :title="optional($u->lessonTypes()->find($priceId))->type === \App\Models\LessonType::TYPE_GROUP ? 'Групповые занятия' : 'Индивидуальные занятия'" :sub="$u->name" close="closeModal">
            <div class="flex flex-col gap-2" role="radiogroup" aria-label="Цена указана">
                <span class="text-t2 font-medium">Цена указана</span>
                <x-ui.option type="radio" name="pricePayment" value="per_lesson" wire:model.live="pricePayment" title="За занятие" />
                <x-ui.option type="radio" name="pricePayment" value="monthly" wire:model.live="pricePayment" title="За месяц" />
            </div>
            <div @class(['grid grid-cols-1 gap-4', 'lg:grid-cols-3' => $monthly, 'lg:grid-cols-2' => ! $monthly])>
                <x-ui.field :label="$monthly ? 'Цена за месяц, ₽' : 'Цена за занятие, ₽'" name="price" type="number" min="0" wire:model="price" />
                <x-ui.field label="Длительность, минут" name="priceDuration" type="number" min="1" wire:model="priceDuration" />
                @if ($monthly)<x-ui.field label="Занятий в неделю" name="priceCount" type="number" min="1" wire:model="priceCount" />@endif
            </div>
            <x-slot:note>Новая цена сразу появится в каталоге</x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="closeModal">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="savePrice" wire:loading.attr="disabled" wire:target="savePrice">Сохранить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
