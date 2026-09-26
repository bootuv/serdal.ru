<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Пользователи" :sub="$sub">
        <x-slot:actions>
            <x-ui.btn variant="dark" icon="plus" wire:click="openAdd">Добавить пользователя</x-ui.btn>
        </x-slot:actions>
    </x-ui.page-head>

    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:gap-6">
            <div class="flex flex-1 gap-6 overflow-x-auto border-b border-line" role="tablist" aria-label="Кого показать">
                @foreach (['teachers' => 'Учителя', 'students' => 'Ученики', 'admins' => 'Администраторы'] as $key => $label)
                    <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                            @class(['-mb-px inline-flex h-11 shrink-0 items-center gap-2 border-b-2 text-t1-s',
                                    'border-ink font-semibold text-ink' => $tab === $key,
                                    'border-transparent font-medium text-muted hover:text-ink' => $tab !== $key])>
                        {{ $label }}<span class="text-t3 font-medium text-muted">{{ $counts[$key] }}</span>
                    </button>
                @endforeach
            </div>
            <div class="lg:border-b lg:border-line lg:pb-2">
                <x-ui.search placeholder="Имя, почта или телефон" wire:model.live.debounce.300ms="search" />
            </div>
        </div>

        @if ($tab !== 'admins')
            <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Фильтры">
                @if ($tab === 'teachers')
                    <x-ui.filter :label="$subjects[$subject] ?? 'Предмет'" :active="$subject !== ''">
                        <x-ui.menu-item wire:click="$set('subject', '')">Все предметы @if ($subject === '')<x-ui.icon name="check" size="s" class="ml-auto" />@endif</x-ui.menu-item>
                        @foreach ($subjects as $id => $name)
                            <x-ui.menu-item wire:click="$set('subject', '{{ $id }}')" wire:key="subj-{{ $id }}">{{ $name }} @if ((string) $id === $subject)<x-ui.icon name="check" size="s" class="ml-auto" />@endif</x-ui.menu-item>
                        @endforeach
                    </x-ui.filter>
                    <x-ui.filter :label="$directs[$direct] ?? 'Направление'" :active="$direct !== ''">
                        <x-ui.menu-item wire:click="$set('direct', '')">Все направления @if ($direct === '')<x-ui.icon name="check" size="s" class="ml-auto" />@endif</x-ui.menu-item>
                        @foreach ($directs as $id => $name)
                            <x-ui.menu-item wire:click="$set('direct', '{{ $id }}')" wire:key="dir-{{ $id }}">{{ $name }} @if ((string) $id === $direct)<x-ui.icon name="check" size="s" class="ml-auto" />@endif</x-ui.menu-item>
                        @endforeach
                    </x-ui.filter>
                    @php
                        $gradeLabel = $grades
                            ? 'Класс: ' . collect($grades)->map(fn ($g) => mb_strtolower($gradeOptions[$g] ?? $g))->implode(', ')
                            : 'Класс';
                    @endphp
                    <x-ui.filter :label="$gradeLabel" :active="$grades !== []" keep>
                        <div class="flex flex-col gap-4 p-3">
                            <div class="flex flex-wrap gap-2">
                                @foreach ($gradeOptions as $key => $label)
                                    <x-ui.chip :square="is_numeric($key)" :on="in_array((string) $key, $grades, true)" wire:click="toggleGrade('{{ $key }}')" wire:key="grade-{{ $key }}">{{ $label }}</x-ui.chip>
                                @endforeach
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <button type="button" class="link text-t2" wire:click="clearGrades">Очистить</button>
                                <x-ui.btn size="s" x-on:click="open = false">Готово</x-ui.btn>
                            </div>
                        </div>
                    </x-ui.filter>
                    <x-ui.filter :label="$tariffs[$tariff] ?? 'Тариф'" :active="$tariff !== ''">
                        <x-ui.menu-item wire:click="$set('tariff', '')">Все тарифы @if ($tariff === '')<x-ui.icon name="check" size="s" class="ml-auto" />@endif</x-ui.menu-item>
                        @foreach ($tariffs as $id => $name)
                            <x-ui.menu-item wire:click="$set('tariff', '{{ $id }}')" wire:key="tar-{{ $id }}">{{ $name }} @if ((string) $id === $tariff)<x-ui.icon name="check" size="s" class="ml-auto" />@endif</x-ui.menu-item>
                        @endforeach
                    </x-ui.filter>
                    <x-ui.filter toggle label="Первые шаги не пройдены" :active="$onboarding" wire:click="$toggle('onboarding')" />
                @else
                    <x-ui.filter toggle label="Без учителя" :active="$noTeacher" wire:click="$toggle('noTeacher')" />
                @endif
                @if ($anyFilter)
                    <button type="button" class="link ml-2 text-t2" wire:click="resetFilters">Сбросить</button>
                @endif
            </div>
        @endif

        <x-ui.card :aria-label="['teachers' => 'Учителя', 'students' => 'Ученики', 'admins' => 'Администраторы'][$tab]">
            @if ($rows->isEmpty())
                <p class="text-t2 text-muted">
                    @if ($filtered)
                        Никого не нашли — измените запрос@if ($tab !== 'admins') или <button type="button" class="link" wire:click="resetFilters">сбросьте фильтры</button>@endif
                    @else
                        Пока пусто — добавьте первого человека кнопкой «Добавить пользователя».
                    @endif
                </p>
            @else
                <div class="hidden gap-4 text-t3 font-medium text-muted lg:flex" aria-hidden="true">
                    @if ($tab === 'teachers')
                        <span class="flex-1">Учитель</span><span class="w-40">Тариф</span><span class="w-16">Ученики</span><span class="w-44">Отметки</span>
                    @elseif ($tab === 'students')
                        <span class="flex-1">Ученик</span><span class="w-44">Учителя</span><span class="w-40">Последнее занятие</span><span class="w-40">Отметки</span>
                    @else
                        <span class="flex-1">Администратор</span><span class="w-44">Последний вход</span>
                    @endif
                    <span class="w-5"></span>
                </div>
                <x-ui.list>
                    @foreach ($rows as $u)
                        <x-ui.row :href="route('cabinet.admin.user', ['user' => $u['id']])" wire:key="user-{{ $u['id'] }}">
                            <x-ui.avatar :user="$u['user']" />
                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="truncate text-t1 font-medium">{{ $u['name'] }}</span>
                                <span class="truncate text-t2 text-muted">{{ $u['sub'] }}</span>
                            </div>
                            @if ($tab === 'teachers')
                                <div class="hidden w-40 shrink-0 flex-col gap-1 lg:flex">
                                    <span @class(['text-t1-s font-medium', 'text-muted' => $u['tariffNone']])>{{ $u['tariff'] }}</span>
                                    @if ($u['term'])<span @class(['truncate text-t2', 'font-semibold text-ink' => $u['termUrgent'], 'text-muted' => ! $u['termUrgent']])>{{ $u['term'] }}</span>@endif
                                </div>
                                <span @class(['hidden w-16 shrink-0 lg:block', 'text-t1-s font-medium' => $u['students'], 'text-t2 text-muted' => ! $u['students']])>{{ $u['students'] ?: 'нет' }}</span>
                                <div class="flex shrink-0 flex-wrap justify-end gap-2 lg:w-44 lg:justify-start">
                                    @foreach ($u['marks'] as $m)<x-ui.badge :tone="$m['tone']">{{ $m['label'] }}</x-ui.badge>@endforeach
                                </div>
                            @elseif ($tab === 'students')
                                <span @class(['hidden w-44 shrink-0 truncate lg:block', 'text-t1-s font-medium' => $u['teachers'] !== '', 'text-t2 text-muted' => $u['teachers'] === ''])>{{ $u['teachers'] ?: 'пока нет учителя' }}</span>
                                <span @class(['hidden w-40 shrink-0 lg:block', 'text-t1-s font-medium' => $u['last'], 'text-t2 text-muted' => ! $u['last']])>{{ $u['last'] ?: 'ещё не было' }}</span>
                                <div class="flex shrink-0 flex-wrap justify-end gap-2 lg:w-40 lg:justify-start">
                                    @foreach ($u['marks'] as $m)<x-ui.badge :tone="$m['tone']">{{ $m['label'] }}</x-ui.badge>@endforeach
                                </div>
                            @else
                                @foreach ($u['marks'] as $m)<x-ui.badge :tone="$m['tone']">{{ $m['label'] }}</x-ui.badge>@endforeach
                                <span class="hidden w-44 shrink-0 text-t2 text-muted lg:block">{{ $u['seen'] }}</span>
                            @endif
                        </x-ui.row>
                    @endforeach
                </x-ui.list>
                <div class="flex min-h-9 items-center justify-between gap-4 border-t border-line pt-4 text-t2 text-muted">
                    <span>{{ $foot }}</span>
                    @if ($canMore)
                        <x-ui.btn size="s" wire:click="more" wire:loading.attr="disabled" wire:target="more">Показать ещё</x-ui.btn>
                    @endif
                </div>
            @endif
        </x-ui.card>
    </div>

    {{-- Окно «Добавить пользователя» (макет AdminUsersAdd) --}}
    @if ($adding)
        <x-ui.modal title="Добавить пользователя" close="closeAdd">
            <div class="flex flex-col gap-2">
                <span class="text-t2 font-medium">Роль</span>
                <x-ui.seg :items="['teacher' => 'Учитель', 'student' => 'Ученик', 'admin' => 'Администратор']" model="role" :active="$role" aria-label="Роль" />
            </div>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <x-ui.field label="Фамилия" name="lastName" wire:model="lastName" autocomplete="off" />
                <x-ui.field label="Имя" name="firstName" wire:model="firstName" autocomplete="off" />
                <x-ui.field label="Отчество" name="middleName" wire:model="middleName" optional autocomplete="off" />
            </div>
            <x-ui.field label="Почта" name="email" type="email" wire:model.live.debounce.500ms="email" placeholder="name@mail.ru" autocomplete="off" />
            <div class="flex flex-col gap-2" role="radiogroup" aria-label="Первый вход">
                <span class="text-t2 font-medium">Первый вход</span>
                <x-ui.option type="radio" name="mode" value="link" wire:model.live="mode" title="Отправить ссылку для входа" sub="Человек сам задаст пароль по ссылке из письма" />
                <x-ui.option type="radio" name="mode" value="pass" wire:model.live="mode" title="Задать пароль" sub="Сообщите пароль сами — на почту он не придёт" />
            </div>
            @if ($mode === 'pass')
                <x-ui.password label="Пароль" name="password" wire:model="password" autocomplete="new-password" hint="Не короче 8 символов" />
            @endif
            <x-slot:note>{{ $roleNote }}</x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="closeAdd">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="add" wire:loading.attr="disabled" wire:target="add">Добавить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($toast)
        <x-ui.toast-action :message="$toast" close="hideToast" :href="$toastUrl" link-label="Открыть карточку" wire:key="toast-{{ md5($toast . $toastUrl) }}" />
    @endif
</div>
