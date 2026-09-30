{{-- Вход в класс: ждём учителя (проверка каждые 30 с) → «Войти в класс». Гость вводит имя; вошедший входит под своим. --}}
<div class="flex flex-col gap-8" wire:poll.30s="checkRoomStatus">
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
            <h1 class="text-h1-m font-medium lg:text-h1">{{ $room->name }}</h1>
            @if ($when)<p class="text-t1 text-muted">{{ $when }}</p>@endif
        </div>
        @if ($teacher)
            <div class="flex items-center gap-3">
                <x-ui.avatar :user="$teacher" />
                <span class="flex min-w-0 flex-col">
                    <span class="truncate text-t1 font-medium">{{ $teacher->name }}</span>
                    <span class="text-t2 text-muted">{{ $user ? 'ваш учитель' : 'приглашает вас на занятие' }}</span>
                </span>
            </div>
        @endif
    </div>

    @if (! $isRoomRunning)
        <div class="flex items-start gap-3 rounded-lg bg-soft p-4">
            <x-ui.icon name="clock" class="text-muted" />
            <div class="flex flex-col gap-1">
                <span class="text-t1-s font-medium">{{ $user ? 'Ждём учителя' : 'Занятие ещё не началось' }}</span>
                <span class="text-t2 text-muted">
                    {{ $teacher?->name ?? 'Учитель' }} ещё не начал занятие. Кнопка входа появится здесь сама — обновлять страницу не нужно.
                    Проверяем каждые 30 секунд ·
                    <button type="button" wire:click="checkRoomStatus" class="link">Проверить сейчас</button>
                </span>
            </div>
        </div>
        @if ($user)
            <div class="flex flex-wrap gap-2">
                @if ($chatUrl)<x-ui.btn icon="chat" :href="$chatUrl">Написать учителю</x-ui.btn>@endif
                @if ($materialsUrl)<x-ui.btn icon="folder" :href="$materialsUrl">Материалы</x-ui.btn>@endif
            </div>
        @endif
    @elseif ($full !== null)
        {{-- Лимит участников занятия набран: в BBB не пустили, учителю ушло уведомление --}}
        <div class="flex flex-col gap-6">
            <div class="flex items-start gap-3 rounded-lg bg-danger-bg p-4 text-danger-fg">
                <x-ui.icon name="users" />
                <div class="flex flex-col gap-1">
                    <span class="text-t1-s font-medium">В классе нет свободных мест</span>
                    <span class="text-t2">
                        @if ($full > 0)В этом занятии может быть до {{ plural_ru($full, 'участника', 'участников', 'участников') }}, считая учителя, — все места заняты.@elseВсе места в этом занятии заняты.@endif
                        Мы сообщили учителю. Войти получится, когда место освободится.
                    </span>
                </div>
            </div>
            <div class="flex flex-col gap-3">
                <x-ui.btn size="l" icon="repeat" :href="route('rooms.connect', $room)" class="w-full">Попробовать ещё раз</x-ui.btn>
                @if ($chatUrl)<x-ui.btn size="l" icon="chat" :href="$chatUrl" class="w-full">Написать учителю</x-ui.btn>@endif
            </div>
        </div>
    @else
        <div class="flex flex-col gap-6">
            <div class="flex items-center gap-3 rounded-lg bg-ok-bg p-4 text-ok-fg">
                <x-ui.icon name="check" />
                <span class="text-t1-s font-medium">Занятие началось — можно входить</span>
            </div>
            @if ($user)
                <div class="flex flex-col gap-3">
                    <x-ui.btn variant="primary" size="l" icon="video" :href="route('rooms.connect', $room)" class="w-full">Войти в класс</x-ui.btn>
                    <span class="text-t2 text-muted">Браузер попросит доступ к микрофону и камере.</span>
                </div>
            @else
                <form wire:submit="submitName" class="flex flex-col gap-6">
                    <x-ui.field label="Как вас зовут" name="name" size="l" autocomplete="name" placeholder="Имя и фамилия" wire:model="name" hint="Это имя увидят участники занятия" autofocus />
                    <div class="flex flex-col gap-3">
                        <x-ui.btn type="submit" variant="primary" size="l" icon="video" class="w-full" wire:loading.attr="disabled" wire:target="submitName">Войти в класс</x-ui.btn>
                        <span class="text-t2 text-muted">Браузер попросит доступ к микрофону и камере.</span>
                    </div>
                </form>
            @endif
        </div>
    @endif

    @if ($user)
        <a href="{{ $cabinetUrl }}" class="link inline-flex items-center gap-2 self-start text-t1-s"><x-ui.icon name="arrow-left" size="s" />В кабинет</a>
    @else
        <div class="flex items-center gap-4 text-t2 text-muted" aria-hidden="true"><span class="h-px flex-1 bg-line"></span>или<span class="h-px flex-1 bg-line"></span></div>
        <p class="text-t2 text-muted">Есть аккаунт на Serdal? <a href="{{ route('login') }}" class="link">Войдите</a> — занятие появится в вашем расписании.</p>
    @endif
</div>
