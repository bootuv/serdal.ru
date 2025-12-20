<div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8 flex flex-col justify-center">
    @if ($isSubmitted)
        <div class="max-w-md w-full mx-auto bg-white dark:bg-gray-800 p-8 rounded-lg shadow-lg text-center animate-fade-in">
            <div
                class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 dark:bg-green-900 mb-6">
                <svg class="h-8 w-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">
                Заявка успешно отправлена!
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-8">
                Мы рассмотрим вашу анкету в ближайшее время. Ответ с результатами рассмотрения будет отправлен на вашу
                электронную почту.
            </p>
            <div>
                <a href="/"
                    class="inline-flex items-center justify-center px-5 py-2 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                    Вернуться на главную
                </a>
            </div>
        </div>
    @else
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
                            Нажимая кнопку "Отправить заявку", вы соглашаетесь с условиями обработки персональных данных.
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
    @endif
</div>