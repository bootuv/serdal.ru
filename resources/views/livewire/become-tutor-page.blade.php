<div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8 flex flex-col justify-center">
    <div class="max-w-3xl w-full mx-auto space-y-8">
        <div class="text-center">
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900 dark:text-white">
                Стать преподавателем
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Заполните анкету, и мы свяжемся с вами после рассмотрения заявки.
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 py-8 px-4 shadow sm:rounded-lg sm:px-10">
            <form wire:submit="create" class="space-y-6">
                {{ $this->form }}

                <div>
                    <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                        <span wire:loading.remove>Отправить заявку</span>
                        <span wire:loading>Отправка...</span>
                    </button>
                    <p class="mt-2 text-center text-xs text-gray-500">
                        Нажимаю кнопку "Отправить заявку", вы соглашаетесь с условиями обработки персональных данных.
                    </p>
                </div>
            </form>
        </div>

        <div class="text-center">
            <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                Вернуться на страницу входа
            </a>
        </div>
    </div>
</div>