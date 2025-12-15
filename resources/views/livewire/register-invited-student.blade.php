<div class="flex min-h-screen flex-col justify-center px-6 py-12 lg:px-8 bg-gray-50 dark:bg-gray-900">
    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        <h2 class="mt-10 text-center text-2xl font-bold leading-9 tracking-tight text-gray-900 dark:text-white">
            Регистрация ученика
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
            Создайте аккаунт для доступа к платформе
        </p>
    </div>

    <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm">
        <form class="space-y-6" wire:submit="register">
            <div class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="last_name"
                        class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Фамилия</label>
                    <div class="mt-2">
                        <input wire:model="last_name" id="last_name" name="last_name" type="text" required
                            class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 dark:bg-white/5 dark:text-white dark:ring-white/10">
                    </div>
                    @error('last_name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div class="sm:col-span-1">
                    <label for="first_name"
                        class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Имя</label>
                    <div class="mt-2">
                        <input wire:model="first_name" id="first_name" name="first_name" type="text" required
                            class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 dark:bg-white/5 dark:text-white dark:ring-white/10">
                    </div>
                    @error('first_name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div class="sm:col-span-1">
                    <label for="middle_name"
                        class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Отчество</label>
                    <div class="mt-2">
                        <input wire:model="middle_name" id="middle_name" name="middle_name" type="text" required
                            class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 dark:bg-white/5 dark:text-white dark:ring-white/10">
                    </div>
                    @error('middle_name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>

            <div>
                <label for="email"
                    class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Email</label>
                <div class="mt-2">
                    <input wire:model="email" id="email" name="email" type="email" autocomplete="email" required
                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 dark:bg-white/5 dark:text-white dark:ring-white/10">
                </div>
                @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Телефон
                    (необязательно)</label>
                <div class="mt-2">
                    <input wire:model="phone" id="phone" name="phone" type="text"
                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 dark:bg-white/5 dark:text-white dark:ring-white/10">
                </div>
                @error('phone') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="password"
                    class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Пароль</label>
                <div class="mt-2">
                    <input wire:model="password" id="password" name="password" type="password" required
                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 dark:bg-white/5 dark:text-white dark:ring-white/10">
                </div>
                @error('password') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="password_confirmation"
                    class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Подтверждение
                    пароля</label>
                <div class="mt-2">
                    <input wire:model="password_confirmation" id="password_confirmation" name="password_confirmation"
                        type="password" required
                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 dark:bg-white/5 dark:text-white dark:ring-white/10">
                </div>
            </div>

            <div>
                <button type="submit"
                    class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Зарегистрироваться
                </button>
            </div>
        </form>
    </div>
</div>