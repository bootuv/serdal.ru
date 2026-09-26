{{-- Вход: почта и пароль → кабинет своей роли. Заблокированный аккаунт — экран «Доступ приостановлен». --}}
<div class="flex flex-col gap-8">
    @if ($blocked)
        <span class="flex size-16 items-center justify-center rounded-lg bg-soft" aria-hidden="true"><x-ui.icon name="lock" /></span>
        <div class="flex flex-col gap-2">
            <h1 class="text-h1-m font-medium lg:text-h1">Доступ к кабинету приостановлен</h1>
            <p class="text-t1 text-muted">Аккаунт <span class="font-semibold text-ink">{{ $blocked }}</span> отключён. Напишите нам с этой почты — разберёмся и подскажем, как вернуть доступ.</p>
        </div>
        <x-ui.btn variant="primary" size="l" icon="mail" href="mailto:info@serdal.ru" class="w-full">Написать в поддержку</x-ui.btn>
        <button type="button" wire:click="back" class="link inline-flex items-center gap-2 self-start text-t1-s"><x-ui.icon name="arrow-left" size="s" />Войти в другой аккаунт</button>
    @else
        <div class="flex flex-col gap-2">
            <h1 class="text-h1-m font-medium lg:text-h1">Вход в кабинет</h1>
            <p class="text-t1 text-muted">Для учителей и учеников.</p>
        </div>

        <form wire:submit="login" class="flex flex-col gap-6">
            <div class="flex flex-col gap-4">
                <x-ui.field label="Email" name="email" type="email" size="l" autocomplete="email" inputmode="email" placeholder="you@mail.ru" wire:model="email" autofocus />
                <x-ui.password label="Пароль" name="password" size="l" autocomplete="current-password" wire:model="password">
                    <x-slot:aside><a href="{{ route('password.request') }}" class="link text-t2">Забыли пароль?</a></x-slot:aside>
                </x-ui.password>
            </div>
            <x-ui.btn type="submit" variant="primary" size="l" class="w-full" wire:loading.attr="disabled" wire:target="login">Войти</x-ui.btn>
        </form>

        <div class="flex items-center gap-4 text-t2 text-muted" aria-hidden="true"><span class="h-px flex-1 bg-line"></span>или<span class="h-px flex-1 bg-line"></span></div>

        <div class="flex flex-col gap-3">
            <x-ui.btn size="l" :href="route('become-tutor')" class="w-full">Стать репетитором</x-ui.btn>
            <p class="text-t2 text-muted">Вы ученик? Попросите у учителя ссылку-приглашение.</p>
        </div>
    @endif
</div>
