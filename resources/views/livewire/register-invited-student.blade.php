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
        </div>

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
                    @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
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
                    Зарегистрироваться
                </button>
            </div>
        </form>
    </div>
</div>