{{-- Регистрация ученика по ссылке-приглашению: анкета → «Проверьте почту» с кодом из 6 цифр. --}}
<div class="flex flex-col gap-8">
    @if ($step === 1)
        <div class="flex flex-col gap-2">
            <h1 class="text-h1-m font-medium lg:text-h1">{{ $teacherName ? $teacherName . ' приглашает вас заниматься' : 'Создайте аккаунт ученика' }}</h1>
            <p class="text-t1 text-muted">Уже есть аккаунт? <a href="{{ $loginUrl ?: route('login') }}" class="link">Войти</a></p>
        </div>

        <form wire:submit="register" class="flex flex-col gap-6" novalidate>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Фамилия" name="last_name" size="l" autocomplete="family-name" wire:model="last_name" />
                <x-ui.field label="Имя" name="first_name" size="l" autocomplete="given-name" wire:model="first_name" />
                <x-ui.field label="Отчество" name="middle_name" size="l" optional autocomplete="additional-name" wire:model="middle_name" />
                <x-ui.field label="Телефон" name="phone" type="tel" size="l" optional autocomplete="tel" inputmode="tel" placeholder="+7 900 000-00-00" wire:model="phone" />
            </div>

            {{-- Почта и пароль — отдельным блоком «Вход в Serdal»: ученики путали пароль с паролем от почты --}}
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <h2 class="text-t1 font-medium">Вход в Serdal</h2>
                    <p class="text-t2 text-muted">С этой почтой и паролем вы будете входить в личный кабинет</p>
                </div>
                <div class="flex flex-col gap-2">
                    <x-ui.field label="Почта" name="email" type="email" size="l" autocomplete="email" inputmode="email" placeholder="name@mail.ru" hint="Пришлём на неё код подтверждения" wire:model="email" />
                    @if ($emailTaken)<a href="{{ $loginUrl ?: route('login') }}" class="link self-start text-t2">Войти с этой почтой</a>@endif
                </div>
                <div class="flex flex-col gap-2">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-ui.password label="Придумайте пароль" name="password" size="l" autocomplete="new-password" wire:model="password" />
                        <x-ui.password label="Повторите его" name="password_confirmation" size="l" autocomplete="new-password" wire:model="password_confirmation" />
                    </div>
                    @unless ($errors->has('password'))<span class="text-t3 text-muted">Минимум 8 символов</span>@endunless
                </div>
            </div>

            {{-- Согласие с условиями: галочка как у x-ui.option, но без рамки строки — по макету --}}
            <label class="flex cursor-pointer items-start gap-3 text-t2">
                <input type="checkbox" wire:model="agree" class="peer sr-only" aria-invalid="{{ $errors->has('agree') ? 'true' : 'false' }}">
                <span @class([
                    'flex size-6 shrink-0 items-center justify-center rounded-sm text-transparent peer-checked:bg-ink peer-checked:text-white peer-checked:shadow-none peer-focus-visible:ring-2 peer-focus-visible:ring-ink peer-focus-visible:ring-offset-2',
                    'shadow-outline' => ! $errors->has('agree'),
                    'shadow-outline-ink' => $errors->has('agree'),
                ]) aria-hidden="true"><x-ui.icon name="check" size="s" /></span>
                <span class="flex min-w-0 flex-col gap-1">
                    <span>Я принимаю <a href="{{ route('terms') }}" target="_blank" class="link">условия использования</a> и <a href="{{ route('privacy') }}" target="_blank" class="link">политику конфиденциальности</a></span>
                    @error('agree')<span class="font-medium text-danger-fg">{{ $message }}</span>@enderror
                </span>
            </label>

            @error('code_expired')<p class="text-t2 font-medium text-danger-fg">{{ $message }}</p>@enderror
            @error('mail_failed')<p class="text-t2 font-medium text-danger-fg">{{ $message }}</p>@enderror

            <x-ui.btn type="submit" variant="primary" size="l" class="w-full" wire:loading.attr="disabled" wire:target="register">Получить код на почту</x-ui.btn>
        </form>
    @else
        <div class="flex flex-col gap-2">
            <h1 class="text-h1-m font-medium lg:text-h1">Проверьте почту</h1>
            <p class="text-t1 text-muted">Отправили код из 6 цифр на <x-ui.em>{{ $email }}</x-ui.em>. Письма нет — загляните в «Спам».</p>
        </div>

        <form wire:submit="verifyAndRegister" class="flex flex-col gap-6" novalidate>
            <x-ui.code-input :hint="'Код действует ' . plural_ru($ttl, 'минуту', 'минуты', 'минут')" />

            <x-ui.btn type="submit" variant="primary" size="l" class="w-full" wire:loading.attr="disabled" wire:target="verifyAndRegister">Подтвердить и создать аккаунт</x-ui.btn>

            <div class="flex items-center justify-between gap-4">
                @if ($codeResent)
                    <x-ui.badge tone="ok">Новый код отправлен</x-ui.badge>
                @else
                    <button type="button" wire:click="resendCode" wire:loading.attr="disabled" wire:target="resendCode" class="link text-t2">Отправить код ещё раз</button>
                @endif
                <button type="button" wire:click="backToForm" class="link text-t2 text-muted">Изменить данные</button>
            </div>
        </form>
    @endif
</div>
