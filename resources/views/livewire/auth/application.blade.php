{{-- Заявка учителя: анкета → «Заявка отправлена». --}}
<div class="flex flex-col gap-8">
    @if ($isSubmitted)
        <span class="flex size-16 items-center justify-center rounded-lg bg-ok-bg text-ok-fg" aria-hidden="true"><x-ui.icon name="check" /></span>
        <div class="flex flex-col gap-2">
            <h1 class="text-h1-m font-medium lg:text-h1">Заявка отправлена</h1>
            <p class="text-t1 text-muted">Спасибо{{ $sentName ? ', ' . $sentName : '' }}! Когда проверим анкету, пришлём доступ на <x-ui.em>{{ $sentEmail }}</x-ui.em>. Письма нет — загляните в «Спам».</p>
        </div>
        <x-ui.btn size="l" :href="url('/')" class="self-start">На главную</x-ui.btn>
    @else
        <div class="flex flex-col gap-2">
            <h1 class="text-h1-m font-medium lg:text-h1">Станьте репетитором на {{ \App\Support\Seo::SITE_NAME }}</h1>
            <p class="text-t1 text-muted">Проверим анкету и пришлём доступ в кабинет на почту. Начать можно бесплатно.</p>
            <p class="text-t1 text-muted">Уже есть аккаунт? <a href="{{ route('login') }}" class="link">Войти</a></p>
        </div>

        @if ($referrer)
            {{-- Приглашение от коллеги по партнёрской программе --}}
            <div class="flex flex-col gap-1 rounded-lg bg-promo p-4 text-t2">
                <p class="font-semibold">Приглашение от {{ $referrer->name }}</p>
                <p class="text-muted">
                    @if ($referralBonus > 0)
                        После первой оплаты тарифа вы получите <x-ui.em>+{{ $referralBonus }} {{ \App\Services\SubscriptionService::lessonsWord($referralBonus) }} в подарок</x-ui.em>. Бонусные занятия не сгорают.
                    @else
                        Заполните анкету — после одобрения заявки вы сможете сразу начать работу.
                    @endif
                </p>
                <button type="button" wire:click="declineReferral" class="link self-start">Меня никто не приглашал</button>
            </div>
        @endif

        @if ($tariff)
            <p class="rounded-lg bg-soft p-4 text-t2">
                Вы выбрали тариф «{{ $tariff->name }}»{{ $tariff->isFree() ? ' — бесплатный' : ' — ' . number_format($tariff->price, 0, ',', ' ') . ' ₽ в месяц' }}.
                После одобрения заявки и настройки профиля {{ $tariff->isFree() ? 'он подключится автоматически.' : 'вы сможете сразу перейти к его оплате.' }}
            </p>
        @endif

        <form wire:submit="create" class="flex flex-col gap-6" novalidate>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Фамилия" name="data.last_name" size="l" autocomplete="family-name" wire:model="data.last_name" />
                <x-ui.field label="Имя" name="data.first_name" size="l" autocomplete="given-name" wire:model="data.first_name" />
                <x-ui.field label="Отчество" name="data.middle_name" size="l" autocomplete="additional-name" wire:model="data.middle_name" />
                <x-ui.field label="Телефон" name="data.phone" type="tel" size="l" autocomplete="tel" inputmode="tel" placeholder="+7 900 000-00-00" wire:model="data.phone" />
                <div class="flex flex-col gap-2 sm:col-span-2">
                    <x-ui.field label="Почта" name="data.email" type="email" size="l" autocomplete="email" inputmode="email" placeholder="Ваша почта" wire:model="data.email" />
                    @if ($emailTaken)<a href="{{ route('login') }}" class="link self-start text-t2">Войти в кабинет</a>@endif
                </div>
            </div>

            <div class="flex flex-col gap-2" role="group" aria-labelledby="app-subj">
                <span id="app-subj" class="text-t2 font-medium">Что вы преподаёте</span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($subjectOptions as $id => $name)
                        <x-ui.chip :on="in_array($id, array_map('intval', $data['subjects']), true)" wire:click="toggle('subjects', {{ $id }})" wire:key="subj-{{ $id }}">{{ $name }}</x-ui.chip>
                    @endforeach
                </div>
                @error('data.subjects')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            </div>

            @if ($directOptions->isNotEmpty())
            <div class="flex flex-col gap-2" role="group" aria-labelledby="app-dir">
                <span id="app-dir" class="text-t2 font-medium">Направления</span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($directOptions as $id => $name)
                        <x-ui.chip :on="in_array($id, array_map('intval', $data['directs']), true)" wire:click="toggle('directs', {{ $id }})" wire:key="dir-{{ $id }}">{{ $name }}</x-ui.chip>
                    @endforeach
                </div>
                @error('data.directs')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            </div>
            @endif

            <div class="flex flex-col gap-2" role="group" aria-labelledby="app-grade">
                <span id="app-grade" class="text-t2 font-medium">С кем занимаетесь</span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($gradeGroups as $key => [$label, $members])
                        <x-ui.chip :on="empty(array_diff($members, array_map('strval', $data['grade'])))" wire:click="toggleGradeGroup('{{ $key }}')" wire:key="grade-{{ $key }}">{{ $label }}</x-ui.chip>
                    @endforeach
                </div>
                @error('data.grade')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            </div>

            <x-ui.field label="Пара слов о себе" name="data.about" rows="4" placeholder="Опыт, образование, с кем занимаетесь" wire:model="data.about" />

            <div class="flex flex-col gap-4">
                <x-ui.btn type="submit" variant="primary" size="l" class="w-full" wire:loading.attr="disabled" wire:target="create">Отправить заявку</x-ui.btn>
                <p class="text-center text-t3 text-muted">Нажимая кнопку, вы принимаете <a href="{{ route('terms') }}" target="_blank" class="link">условия</a> и <a href="{{ route('privacy') }}" target="_blank" class="link">политику конфиденциальности</a></p>
            </div>
        </form>
    @endif
</div>
