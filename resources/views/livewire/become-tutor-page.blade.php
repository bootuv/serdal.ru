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

            @if ($referrer = $this->referrer())
                @php($bonus = \App\Services\ReferralService::referredBonus())
                {{-- Приглашение от коллеги по партнёрской программе --}}
                <div class="flex items-start gap-4 rounded-lg bg-indigo-50 p-5 ring-1 ring-indigo-200 dark:bg-indigo-500/10 dark:ring-indigo-500/30 sm:items-center">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-white">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-base font-semibold text-gray-900 dark:text-white">
                            Вас пригласил(а) {{ $referrer->name }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            @if ($bonus > 0)
                                После первой оплаты тарифа вы получите
                                <span class="font-semibold text-indigo-700 dark:text-indigo-300">+{{ $bonus }} {{ \App\Services\SubscriptionService::lessonsWord($bonus) }} в подарок</span>.
                                Бонусные занятия не сгорают.
                            @else
                                Заполните анкету — после одобрения заявки вы сможете сразу начать работу.
                            @endif
                        </p>
                    </div>
                </div>
            @endif

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