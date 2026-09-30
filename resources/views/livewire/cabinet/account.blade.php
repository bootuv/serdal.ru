{{-- Почта и пароль: две карточки, в каждой — «Изменить» → форма → код из письма (Cabinet\Account). --}}
<div class="flex flex-col gap-6 lg:gap-8">
    @unless ($embedded)
        <x-ui.page-head title="Почта и пароль" sub="Смена почты и пароля подтверждается кодом из письма" />
    @endunless

    <div class="flex w-full max-w-modal-m flex-col gap-6">
        {{-- Почта --}}
        <x-ui.card aria-labelledby="acc-email">
            <x-ui.card-head id="acc-email" title="Почта">
                @if ($editing !== 'email')
                    <x-slot:action><x-ui.btn size="s" wire:click="open('email')">Изменить</x-ui.btn></x-slot:action>
                @endif
            </x-ui.card-head>
            <p class="text-t1 break-all">{{ $user->email }}</p>

            @if ($editing === 'email' && $step === 'form')
                <form wire:submit="sendCode" class="flex flex-col gap-4 border-t border-line pt-4" novalidate>
                    <x-ui.field label="Новая почта" name="newEmail" type="email" wire:model="newEmail" autocomplete="email" hint="Пришлём на неё код подтверждения" />
                    <x-ui.password label="Текущий пароль" name="currentPassword" wire:model="currentPassword" autocomplete="current-password" />
                    @error('code_expired')<p class="text-t2 font-medium text-danger-fg">{{ $message }}</p>@enderror
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.btn variant="primary" type="submit" wire:loading.attr="disabled" wire:target="sendCode">Получить код</x-ui.btn>
                        <x-ui.btn wire:click="cancel">Отмена</x-ui.btn>
                    </div>
                </form>
            @endif
        </x-ui.card>

        {{-- Пароль --}}
        <x-ui.card aria-labelledby="acc-password">
            <x-ui.card-head id="acc-password" title="Пароль">
                @if ($editing !== 'password')
                    <x-slot:action><x-ui.btn size="s" wire:click="open('password')">Изменить</x-ui.btn></x-slot:action>
                @endif
            </x-ui.card-head>
            <p class="text-t1 tracking-widest" aria-label="Пароль задан">••••••••</p>

            @if ($editing === 'password' && $step === 'form')
                <form wire:submit="sendCode" class="flex flex-col gap-4 border-t border-line pt-4" novalidate>
                    <x-ui.password label="Новый пароль" name="newPassword" wire:model="newPassword" autocomplete="new-password" hint="Минимум 8 символов. Код подтверждения придёт на вашу почту" />
                    @error('code_expired')<p class="text-t2 font-medium text-danger-fg">{{ $message }}</p>@enderror
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.btn variant="primary" type="submit" wire:loading.attr="disabled" wire:target="sendCode">Получить код</x-ui.btn>
                        <x-ui.btn wire:click="cancel">Отмена</x-ui.btn>
                    </div>
                </form>
            @endif
        </x-ui.card>

        @if ($editing && $step === 'code')
            <x-ui.modal :title="$editing === 'email' ? 'Подтвердите новую почту' : 'Подтвердите смену пароля'" close="cancel" width="s">
                <form id="acc-code" wire:submit="confirm" class="flex flex-col gap-4" novalidate>
                    <p class="text-t1 text-muted">Отправили код из 6 цифр на <x-ui.em>{{ $sentTo }}</x-ui.em>. Письма нет — загляните в «Спам».</p>
                    <x-ui.code-input :hint="'Код действует ' . plural_ru($ttl, 'минуту', 'минуты', 'минут')" />
                    @if ($codeResent)
                        <x-ui.badge tone="ok" class="self-start">Новый код отправлен</x-ui.badge>
                    @else
                        <button type="button" wire:click="resendCode" wire:loading.attr="disabled" wire:target="resendCode" class="link self-start text-t2">Отправить код ещё раз</button>
                    @endif
                </form>
                <x-slot:footer>
                    <x-ui.btn wire:click="cancel">Отмена</x-ui.btn>
                    <x-ui.btn variant="primary" type="submit" form="acc-code" wire:loading.attr="disabled" wire:target="confirm">Подтвердить</x-ui.btn>
                </x-slot:footer>
            </x-ui.modal>
        @endif
    </div>
</div>
