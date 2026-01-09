<div class="flex min-h-screen items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
    <div class="w-full max-w-md space-y-8">
        <div class="text-center">
            <img src="{{ asset('images/Logo.svg') }}" alt="Logo" class="mx-auto h-12 w-auto dark:invert">
            <h2 class="mt-6 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Регистрация ученика
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                Заполните форму для создания аккаунта
            </p>
            <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                Уже есть аккаунт? <a href="{{ route('login') }}"
                    class="font-medium text-amber-600 hover:text-amber-500">Войдите</a>
            </p>
        </div>

        @if($step === 1)
            <form class="mt-8 space-y-6" wire:submit="register">
                <div class="space-y-4">

                    <!-- Фамилия -->
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Фамилия
                        </label>
                        <input wire:model="last_name" id="last_name" type="text" required
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-400 focus:border-amber-500 focus:outline-none focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:text-sm">
                        @error('last_name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Имя -->
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Имя
                        </label>
                        <input wire:model="first_name" id="first_name" type="text" required
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-400 focus:border-amber-500 focus:outline-none focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:text-sm">
                        @error('first_name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Отчество -->
                    <div>
                        <label for="middle_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Отчество
                        </label>
                        <input wire:model="middle_name" id="middle_name" type="text" required
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-400 focus:border-amber-500 focus:outline-none focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:text-sm">
                        @error('middle_name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Email
                        </label>
                        <input wire:model="email" id="email" type="email" autocomplete="email" required
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-400 focus:border-amber-500 focus:outline-none focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:text-sm"
                            placeholder="your@email.com">
                        @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{!! $message !!}</p>
                        @enderror
                    </div>

                    <!-- Телефон -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Телефон (необязательно)
                        </label>
                        <input wire:model="phone" id="phone" type="text"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-400 focus:border-amber-500 focus:outline-none focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:text-sm">
                        @error('phone') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <!-- Пароль -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Пароль
                        </label>
                        <input wire:model="password" id="password" type="password" required
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-400 focus:border-amber-500 focus:outline-none focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Минимум 8 символов</p>
                        @error('password') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Подтверждение пароля -->
                    <div>
                        <label for="password_confirmation"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Подтверждение пароля
                        </label>
                        <input wire:model="password_confirmation" id="password_confirmation" type="password" required
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-400 focus:border-amber-500 focus:outline-none focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:text-sm">
                    </div>
                </div>

                <div>
                    <button type="submit"
                        class="group relative flex w-full justify-center rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600">
                        Получить код подтверждения
                    </button>
                </div>
            </form>
        @elseif($step === 2)
            <div class="mt-8 space-y-6">
                <div class="rounded-md bg-blue-50 p-4 dark:bg-blue-900/30">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                Мы отправили 6-значный код подтверждения на <strong>{{ $email }}</strong>
                            </p>
                            <p class="mt-1 text-sm text-blue-600 dark:text-blue-400">
                                Если письмо не пришло, проверьте папку "Спам"
                            </p>
                        </div>
                    </div>
                </div>

                <form wire:submit="verifyAndRegister" class="space-y-6">
                    <!-- Код подтверждения -->
                    <div>
                        <label for="verification_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Код подтверждения
                        </label>
                        <input wire:model="verification_code" id="verification_code" type="text" required autofocus
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-center text-2xl tracking-widest text-gray-900 placeholder-gray-400 focus:border-amber-500 focus:outline-none focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            placeholder="123456" maxlength="6">
                        @error('verification_code') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="flex flex-col space-y-3">
                        <button type="submit"
                            class="group relative flex w-full justify-center rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600">
                            Подтвердить и создать аккаунт
                        </button>

                        <button type="button" wire:click="backToForm"
                            class="text-sm font-medium text-gray-600 hover:text-gray-500 dark:text-gray-400 dark:hover:text-gray-300 text-center">
                            Изменить Email или данные
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>