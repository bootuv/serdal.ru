{{-- Восстановление пароля: почта → «Проверьте почту». --}}
<div class="flex flex-col gap-8">
    @if ($sent)
        <span class="flex size-16 items-center justify-center rounded-lg bg-ok-bg text-ok-fg" aria-hidden="true"><x-ui.icon name="mail" /></span>
        <div class="flex flex-col gap-2">
            <h1 class="text-h1-m font-medium lg:text-h1">Проверьте почту</h1>
            <p class="text-t1 text-muted">Отправили ссылку на <span class="font-semibold text-ink">{{ $email }}</span>. Она действует <span class="font-semibold text-ink">{{ plural_ru($expire, 'минуту', 'минуты', 'минут') }}</span>.</p>
        </div>
        <div class="flex flex-col gap-4" wire:key="round-{{ $round }}" x-data="{ left: {{ $throttle }}, t: null }" x-init="t = setInterval(() => { if (left > 0) left-- }, 1000)" x-on:remove="clearInterval(t)">
            <p class="text-t2 text-muted">
                Письма нет? Проверьте «Спам».
                <span x-show="left > 0">Отправить ещё раз можно через <span class="font-semibold text-ink" x-text="left + ' с'"></span>.</span>
                <button type="button" x-show="left === 0" x-cloak wire:click="send" class="link">Отправить ещё раз</button>
            </p>
            @error('email')<p class="text-t2 font-medium text-danger-fg">{{ $message }}</p>@enderror
            <x-ui.btn size="l" wire:click="back" class="w-full">Указать другой email</x-ui.btn>
        </div>
    @else
        <div class="flex flex-col gap-2">
            <h1 class="text-h1-m font-medium lg:text-h1">Восстановление пароля</h1>
            <p class="text-t1 text-muted">Пришлём ссылку, чтобы задать новый пароль.</p>
        </div>
        <form wire:submit="send" class="flex flex-col gap-6">
            <x-ui.field label="Email" name="email" type="email" size="l" autocomplete="email" inputmode="email" placeholder="you@mail.ru" wire:model="email" autofocus />
            <x-ui.btn type="submit" variant="primary" size="l" class="w-full" wire:loading.attr="disabled" wire:target="send">Прислать ссылку</x-ui.btn>
        </form>
    @endif
    <a href="{{ route('login') }}" class="link inline-flex items-center gap-2 self-start text-t1-s"><x-ui.icon name="arrow-left" size="s" />Вернуться ко входу</a>
</div>
