{{-- Новый пароль: два поля и живые требования; устаревшая ссылка — просим новую. --}}
<div class="flex flex-col gap-8">
    @if ($expired)
        <span class="flex size-16 items-center justify-center rounded-lg bg-danger-bg text-danger-fg" aria-hidden="true"><x-ui.icon name="clock" /></span>
        <div class="flex flex-col gap-2">
            <h1 class="text-h1-m font-medium lg:text-h1">Ссылка устарела</h1>
            <p class="text-t1 text-muted">Ссылка действует {{ plural_ru($expire, 'минуту', 'минуты', 'минут') }} и только один раз. Запросите новую — и откройте её из самого свежего письма.</p>
        </div>
        <x-ui.btn variant="primary" size="l" :href="route('password.request', array_filter(['email' => $email]))" class="w-full">Запросить новую ссылку</x-ui.btn>
    @else
        <div class="flex flex-col gap-2">
            <h1 class="text-h1-m font-medium lg:text-h1">Новый пароль</h1>
            <p class="text-t1 text-muted">Для аккаунта <span class="font-semibold text-ink">{{ $email }}</span>.</p>
        </div>
        <form wire:submit="save" class="flex flex-col gap-6">
            <div class="flex flex-col gap-4">
                <x-ui.password label="Новый пароль" name="password" size="l" autocomplete="new-password" wire:model="password" autofocus />
                <x-ui.password label="Повторите пароль" name="password_confirmation" size="l" autocomplete="new-password" wire:model="password_confirmation" />
                <ul class="flex flex-col gap-2 text-t2" aria-label="Требования к паролю" x-data>
                    <li class="flex items-center gap-2" x-bind:class="($wire.password || '').length >= 8 ? 'text-ok-fg' : 'text-muted'"><x-ui.icon name="check" size="s" />Не короче 8 символов</li>
                    <li class="flex items-center gap-2" x-bind:class="$wire.password && $wire.password === $wire.password_confirmation ? 'text-ok-fg' : 'text-muted'"><x-ui.icon name="check" size="s" />Пароли совпадают</li>
                </ul>
            </div>
            <x-ui.btn type="submit" variant="primary" size="l" class="w-full" wire:loading.attr="disabled" wire:target="save">Сохранить и войти</x-ui.btn>
        </form>
    @endif
    <a href="{{ route('login') }}" class="link inline-flex items-center gap-2 self-start text-t1-s"><x-ui.icon name="arrow-left" size="s" />Вернуться ко входу</a>
</div>
