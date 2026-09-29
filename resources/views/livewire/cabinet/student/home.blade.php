@php
    $hour = now()->hour;
    $greeting = match (true) {
        $hour >= 5 && $hour < 12 => 'Доброе утро',
        $hour >= 12 && $hour < 18 => 'Добрый день',
        $hour >= 18 && $hour < 23 => 'Добрый вечер',
        default => 'Доброй ночи',
    };
@endphp
<div class="flex flex-col gap-6 lg:gap-8">
    @if ($poll)<div wire:poll.60s.visible class="hidden"></div>@endif
    <x-ui.page-head :title="$greeting . ', ' . $firstName" :sub="$today" />

    {{-- Долг перед учителем (макеты PmStudentDebt / PmStudentBlocked): белая карточка над фокус-блоком --}}
    @if ($debt)
        <x-ui.card class="lg:flex-row lg:items-center lg:justify-between lg:gap-6" aria-labelledby="debt">
            <div class="flex min-w-0 flex-col gap-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="debt" class="text-h2 font-medium">{{ $debt['blocked'] ? 'Вход на занятия закрыт' : 'Оплатите занятия' }}</h2>
                    <x-ui.badge tone="danger">{{ $debt['blocked'] ? 'Не оплачено' : 'Просрочено' }}</x-ui.badge>
                </div>
                <p class="text-t2 text-muted">{{ $debt['teacher'] }} · {{ $debt['count'] }}@if ($debt['sum']) — <x-ui.em>{{ $debt['sum'] }}</x-ui.em>@endif. {{ $debt['warning'] }}</p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                @if ($debt['canReport'])
                    <x-ui.btn variant="dark" :href="$debt['reportUrl']">Сообщить об оплате</x-ui.btn>
                @elseif ($debt['waiting'])
                    <span class="text-t2 text-muted">Отправлено учителю · ждёт подтверждения</span>
                @endif
                <x-ui.btn :href="$debt['chatUrl']">Написать учителю</x-ui.btn>
            </div>
        </x-ui.card>
    @endif

    {{-- Важная новость от администрации --}}
    <livewire:cabinet.news-banner />

    {{-- Фокус-блок: следующее занятие --}}
    @if ($next)
        <x-ui.card focus class="lg:flex-row lg:items-center lg:justify-between lg:gap-6" aria-label="Следующее занятие">
            <div class="flex min-w-0 flex-col gap-4">
                <p class="text-t2 text-muted">
                    Следующее занятие@if ($next['until']) · <x-ui.em>{{ $next['until'] }}</x-ui.em>@endif
                </p>
                <div class="flex flex-col gap-1">
                    <h2 class="text-h2 font-medium lg:text-num">{{ $next['title'] }}</h2>
                    <p class="text-t1 text-muted">{{ $next['when'] }}</p>
                </div>
                @if ($next['teacher'])
                    <div class="flex items-center gap-3">
                        <x-ui.avatar :name="$next['teacher']" :id="$next['teacherId']" />
                        <div class="flex flex-col gap-1">
                            <span class="text-t1-s font-medium">{{ $next['teacher'] }}</span>
                            <span class="text-t3 text-muted">Ваш учитель</span>
                        </div>
                    </div>
                @endif
            </div>
            <div class="flex shrink-0 flex-col gap-3 lg:items-end">
                @if ($next['blocked'])
                    <x-ui.btn variant="outline" size="l" icon="lock" disabled>Войти в класс</x-ui.btn>
                    <p class="text-t2 text-muted">Вход закрыт до оплаты · <a href="{{ $next['reportUrl'] ?? $paymentsUrl }}" class="link">сообщить об оплате</a></p>
                @elseif ($next['canJoin'])
                    <x-ui.btn variant="primary" size="l" icon="video" :href="$next['joinUrl']" target="_blank" rel="noopener">Войти в класс</x-ui.btn>
                    <a href="{{ $next['url'] }}" class="link text-t2">Материалы к занятию</a>
                @else
                    {{-- Вход ещё закрыт: крупная кнопка на месте «Войти в класс», чтобы блок не пустел посередине --}}
                    <x-ui.btn size="l" :href="$next['url']">Подробнее о занятии</x-ui.btn>
                    <p class="text-t2 text-muted">{{ $next['joinHint'] }}</p>
                @endif
            </div>
        </x-ui.card>
    @else
        {{-- Занятий нет (макет SyEmptyStudent): учитель и как с ним связаться --}}
        <x-ui.card focus class="lg:flex-row lg:items-center lg:justify-between lg:gap-6" aria-labelledby="e-next">
            <div class="flex min-w-0 flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <h2 id="e-next" class="text-h2 font-medium lg:text-num">Занятий пока нет</h2>
                    <p class="text-t1 text-muted">Учитель ещё не назначил время — пришлём уведомление, когда назначит</p>
                </div>
                @if ($teacher)
                    <div class="flex items-center gap-3">
                        <x-ui.avatar :name="$teacher['name']" :id="$teacher['id']" />
                        <x-ui.text :title="$teacher['name']" :sub="$teacher['sub']" />
                    </div>
                @endif
            </div>
            @if ($teacher)
                <div class="flex shrink-0 flex-col gap-3 lg:items-end">
                    <x-ui.btn variant="primary" size="l" icon="chat" :href="$teacher['chatUrl']">Написать учителю</x-ui.btn>
                    @if ($teacher['telegram'])
                        <span class="text-t2 text-muted">В Telegram · <a href="{{ $teacher['telegramUrl'] }}" target="_blank" rel="noopener" class="link">{{ $teacher['telegram'] }}</a></span>
                    @endif
                </div>
            @endif
        </x-ui.card>
    @endif

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
            {{-- Первые шаги (пустая главная): уведомления в браузере и профиль --}}
            @if ($steps)
                @include('livewire.cabinet.student.partials.first-steps')
            @endif

            {{-- Задания (на пустой главной — только если они есть) --}}
            @if ($next || $homework->isNotEmpty())
            <x-ui.card aria-labelledby="hw">
                <x-ui.card-head id="hw" title="Задания">
                    <x-slot:action><a href="{{ route('cabinet.student.tasks') }}" class="link text-t2">Все задания</a></x-slot:action>
                </x-ui.card-head>
                @if ($homework->isEmpty())
                    <p class="text-t2 text-muted">Пока пусто — новые задания от учителя появятся здесь.</p>
                @else
                    <x-ui.list>
                        @foreach ($homework as $hw)
                            <x-ui.row :href="$hw['url']">
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="truncate text-t1 font-medium">{{ $hw['title'] }}</span>
                                    <span class="text-t2 text-muted">
                                        {{ $hw['teacher'] }}@if ($hw['state'] === 'todo' && $hw['deadline']) · <x-ui.em>сдать {{ $hw['deadline'] }}</x-ui.em>@endif
                                    </span>
                                </div>
                                @if ($hw['state'] === 'revision')<x-ui.badge tone="danger">На доработке</x-ui.badge>
                                @elseif ($hw['state'] === 'overdue')<x-ui.badge tone="danger">Срок прошёл</x-ui.badge>
                                @elseif ($hw['state'] === 'review')<x-ui.badge>На проверке</x-ui.badge>
                                @endif
                            </x-ui.row>
                        @endforeach
                    </x-ui.list>
                @endif
            </x-ui.card>
            @endif

            {{-- Учителя (текущие, затем бывшие) и отзывы о них --}}
            @if ($teacherRows->isNotEmpty())
            <x-ui.card aria-labelledby="teachers">
                <x-ui.card-head id="teachers" title="Учителя" />
                <x-ui.list>
                    @foreach ($teacherRows as $t)
                        <x-ui.row :chevron="false" class="flex-wrap" wire:key="teacher-{{ $t['id'] }}">
                            {{-- Аватар и имя — ссылка на публичную страницу учителя (новая вкладка) --}}
                            <{{ $t['publicUrl'] ? 'a' : 'div' }} @if ($t['publicUrl']) href="{{ $t['publicUrl'] }}" target="_blank" rel="noopener" @endif
                                class="group/teacher flex min-w-0 flex-1 items-center gap-4">
                                <x-ui.avatar :name="$t['name']" :id="$t['id']" />
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span class="truncate text-t1 font-medium underline-offset-4 group-hover/teacher:underline">{{ $t['name'] }}</span>
                                        @if ($t['publicUrl'])<x-ui.icon name="external" size="s" class="text-faint group-hover/teacher:text-ink" />@endif
                                    </span>
                                    @if ($t['sub'])<span class="text-t2 text-muted">{{ $t['sub'] }}</span>@endif
                                    @if ($t['rejected'])<span class="text-t2 text-muted">Новый отзыв этому учителю оставить нельзя</span>@endif
                                </div>
                            </{{ $t['publicUrl'] ? 'a' : 'div' }}>
                            <div class="flex w-full flex-wrap items-center gap-4 lg:w-auto">
                                @if ($t['rejected'])
                                    <x-ui.badge>Отзыв скрыт модератором</x-ui.badge>
                                @else
                                    @if ($t['review'])
                                        <x-ui.stars :value="$t['review']->rating" role="img" />
                                    @endif
                                    @if ($t['canReview'])
                                        <button type="button" class="link text-t2" wire:click="openReview({{ $t['id'] }})">{{ $t['review'] ? 'Изменить отзыв' : 'Оставить отзыв' }}</button>
                                    @elseif ($t['lessons'] === 0 && ! $t['review'])
                                        <span class="text-t2 text-muted">Отзыв — после первого занятия</span>
                                    @endif
                                @endif
                                @if ($t['chat'])
                                    <x-ui.btn size="s" :href="$t['chat']">Написать учителю</x-ui.btn>
                                @endif
                            </div>
                        </x-ui.row>
                    @endforeach
                </x-ui.list>
            </x-ui.card>
            @endif
        </div>

        <div class="flex flex-col gap-6">
            {{-- Оплата: только если есть неоплаченное (кроме долга из карточки выше) --}}
            @if ($payment)
                <x-ui.card aria-labelledby="pay">
                    <x-ui.card-head id="pay" title="К оплате">
                        <x-slot:action><a href="{{ $paymentsUrl }}" class="link text-t2">Подробнее</a></x-slot:action>
                    </x-ui.card-head>
                    <div class="flex flex-col gap-1">
                        <span class="text-num font-medium">{{ $payment['sum'] ?? $payment['countLabel'] }}</span>
                        @if ($payment['facts'] || $payment['late'])
                            <span class="text-t2 text-muted">{{ $payment['facts'] }}@if ($payment['facts'] && $payment['late']) · @endif @if ($payment['late'])<x-ui.em>{{ $payment['late'] }}</x-ui.em>@endif</span>
                        @endif
                    </div>
                    @if ($payment['waiting'])
                        <span class="text-t2 text-muted">Отправлено учителю · ждёт подтверждения</span>
                    @elseif (! $debt)
                        <x-ui.btn variant="dark" :href="$payment['url']" class="self-start">Сообщить об оплате</x-ui.btn>
                    @else
                        <x-ui.btn :href="$payment['url']" class="self-start">Сообщить об оплате</x-ui.btn>
                    @endif
                </x-ui.card>
            @endif

            {{-- Ближайшие занятия --}}
            @if ($week->isNotEmpty())
                <x-ui.card aria-labelledby="week">
                    <x-ui.card-head id="week" title="Дальше">
                        <x-slot:action><a href="{{ route('cabinet.student.schedule') }}" class="link text-t2">Расписание</a></x-slot:action>
                    </x-ui.card-head>
                    <x-ui.list>
                        @foreach ($week as $lesson)
                            <x-ui.row :href="$lesson['url']" wire:key="week-{{ $lesson['id'] }}">
                                <x-ui.date-tile :date="$lesson['start']" :today="$lesson['isToday']" />
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="truncate text-t1-s font-medium">{{ $lesson['start']?->format('H:i') }} · {{ $lesson['title'] }}</span>
                                    <span class="truncate text-t2 text-muted">{{ $lesson['teacher'] }}@if ($lesson['blocked']) · <x-ui.em>вход закрыт</x-ui.em>@endif</span>
                                </div>
                            </x-ui.row>
                        @endforeach
                    </x-ui.list>
                </x-ui.card>
            @endif

            {{-- Успеваемость --}}
            @if ($metrics && $next)
                <x-ui.card aria-labelledby="perf">
                    <x-ui.card-head id="perf" title="Успеваемость" />
                    @if ($teachers->count() > 1)
                        <x-ui.seg model="perfTeacherId" :active="$perfTeacherId" :items="$teachers->pluck('name', 'id')->all()" aria-label="Учитель" />
                    @endif
                    <x-ui.rings :metrics="$metrics" stacked />
                </x-ui.card>
            @endif
        </div>
    </div>

    {{-- Окно отзыва --}}
    @if ($reviewing)
        @php
            $hasReview = (bool) $reviewing['review'];
            $sub = $hasReview
                ? $reviewing['name'] . ' · опубликован ' . \App\Support\HumanDate::at($reviewing['review']->updated_at ?? $reviewing['review']->created_at)
                : implode(' · ', array_filter([$reviewing['name'], $reviewing['teacher']->subjects->pluck('name')->join(', ')]));
        @endphp
        <x-ui.modal :title="$hasReview ? 'Ваш отзыв' : 'Отзыв об учителе'" :sub="$sub" close="closeReview">
            <div class="flex flex-col gap-2">
                <span class="text-t2 font-medium">Оценка</span>
                <div class="flex flex-wrap items-center gap-4">
                    <x-ui.stars :value="$rating" model="rating" />
                    <span class="text-t1 font-semibold">{{ $stars[$rating] ?? '' }}</span>
                </div>
                @error('rating')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            </div>
            <x-ui.field label="Расскажите о занятиях" name="reviewText" rows="6" wire:model="reviewText"
                        placeholder="Например: что получилось благодаря занятиям" hint="Без телефонов и ссылок" maxlength="{{ \App\Models\Review::MAX_TEXT }}" />

            <x-slot:note>
                @if ($reviewing['publicUrl'])
                    {{ $hasReview ? 'Обновится' : 'Появится' }} на <a href="{{ $reviewing['publicUrl'] }}" class="link" target="_blank" rel="noopener">{{ preg_replace('#^https?://#', '', $reviewing['publicUrl']) }}</a>
                @endif
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="closeReview">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveReview" wire:loading.attr="disabled" wire:target="saveReview">{{ $hasReview ? 'Сохранить изменения' : 'Отправить отзыв' }}</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
