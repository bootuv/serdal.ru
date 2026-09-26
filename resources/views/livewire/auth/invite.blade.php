{{-- Регистрация ученика по ссылке-приглашению: анкета → «Проверьте почту» с кодом из 6 цифр. --}}
<div class="flex flex-col gap-8">
    @if ($step === 1)
        <div class="flex flex-col gap-2">
            <h1 class="text-h1-m font-medium lg:text-h1">{{ $teacherName ? $teacherName . ' приглашает вас заниматься' : 'Создайте аккаунт ученика' }}</h1>
            <p class="text-t1 text-muted">Уже есть аккаунт? <a href="{{ $loginUrl ?: route('login') }}" class="link">Войти</a></p>
        </div>

        <form wire:submit="register" class="flex flex-col gap-6" novalidate>
            <div class="flex flex-col gap-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.field label="Фамилия" name="last_name" size="l" autocomplete="family-name" wire:model="last_name" />
                    <x-ui.field label="Имя" name="first_name" size="l" autocomplete="given-name" wire:model="first_name" />
                    <x-ui.field label="Отчество" name="middle_name" size="l" optional autocomplete="additional-name" wire:model="middle_name" />
                    <x-ui.field label="Телефон" name="phone" type="tel" size="l" optional autocomplete="tel" inputmode="tel" placeholder="+7 900 000-00-00" wire:model="phone" />
                </div>
                <div class="flex flex-col gap-2">
                    <x-ui.field label="Почта" name="email" type="email" size="l" autocomplete="email" inputmode="email" placeholder="Ваша почта" wire:model="email" />
                    @if ($emailTaken)<a href="{{ $loginUrl ?: route('login') }}" class="link self-start text-t2">Войти с этой почтой</a>@endif
                </div>
                <div class="flex flex-col gap-2">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-ui.password label="Пароль" name="password" size="l" autocomplete="new-password" wire:model="password" />
                        <x-ui.password label="Повторите пароль" name="password_confirmation" size="l" autocomplete="new-password" wire:model="password_confirmation" />
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

            <x-ui.btn type="submit" variant="primary" size="l" class="w-full" wire:loading.attr="disabled" wire:target="register">Получить код на почту</x-ui.btn>
        </form>
    @else
        <div class="flex flex-col gap-2">
            <h1 class="text-h1-m font-medium lg:text-h1">Проверьте почту</h1>
            <p class="text-t1 text-muted">Отправили код из 6 цифр на <x-ui.em>{{ $email }}</x-ui.em>. Письма нет — загляните в «Спам».</p>
        </div>

        <form wire:submit="verifyAndRegister" class="flex flex-col gap-6" novalidate
              x-data="{
                  d: ['', '', '', '', '', ''],
                  focus(i) { const el = this.$refs['c' + Math.max(0, Math.min(5, i))]; el.focus(); el.select(); },
                  sync() { this.$wire.verification_code = this.d.join(''); },
                  put(i, value) {
                      const digits = String(value).replace(/\D/g, '').split('');
                      if (digits.length === 0) { this.d[i] = ''; this.sync(); return; }
                      const start = digits.length >= 6 ? 0 : i;
                      digits.slice(0, 6 - start).forEach((c, k) => this.d[start + k] = c);
                      this.sync();
                      this.focus(start + digits.length);
                  },
                  erase(i, e) {
                      if (this.d[i] !== '' || i === 0) return;
                      e.preventDefault();
                      this.d[i - 1] = '';
                      this.sync();
                      this.focus(i - 1);
                  },
              }"
              x-init="$nextTick(() => focus(0))">
            <div class="flex flex-col gap-2">
                <label for="code-0" class="text-t2 font-medium">Код из письма</label>
                <div class="flex gap-2" wire:ignore>
                    @for ($i = 0; $i < 6; $i++)
                        <input id="code-{{ $i }}" x-ref="c{{ $i }}" type="text" inputmode="numeric" autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}" aria-label="Цифра {{ $i + 1 }}"
                               x-bind:value="d[{{ $i }}]"
                               x-on:input="put({{ $i }}, $event.target.value); $event.target.value = d[{{ $i }}]"
                               x-on:paste.prevent="put({{ $i }}, $event.clipboardData.getData('text'))"
                               x-on:keydown.backspace="erase({{ $i }}, $event)"
                               x-on:keydown.arrow-left.prevent="focus({{ $i - 1 }})"
                               x-on:keydown.arrow-right.prevent="focus({{ $i + 1 }})"
                               x-on:focus="$event.target.select()"
                               class="field size-13 min-w-0 shrink px-0 text-center text-num font-medium">
                    @endfor
                </div>
                @if ($errors->has('verification_code'))
                    <span class="text-t2 font-medium text-danger-fg">{{ $errors->first('verification_code') }}</span>
                @else
                    <span class="text-t3 text-muted">Код действует {{ plural_ru($ttl, 'минуту', 'минуты', 'минут') }}</span>
                @endif
            </div>

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
