<div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8 flex flex-col justify-center" wire:poll.30s="checkRoomStatus">
    <div class="max-w-md w-full mx-auto">
        {{-- Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden">
            {{-- Header with room name --}}
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-8 text-center">
                <h1 class="text-2xl font-bold text-white">{{ $room->name }}</h1>
                @if($room->user)
                    <p class="mt-2 text-indigo-200 text-sm">Преподаватель: {{ $room->user->name }}</p>
                @endif
            </div>

            <div class="p-6">
                @if($state === 'waiting')
                    {{-- Waiting State --}}
                    <div class="text-center">
                        @if($isRoomRunning)
                            {{-- Room is ready --}}
                            <div
                                class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 dark:bg-green-900 mb-4">
                                <x-heroicon-o-check-circle class="h-10 w-10 text-green-600 dark:text-green-400" />
                            </div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                                Занятие началось!
                            </h2>
                            <p class="text-gray-600 dark:text-gray-400 mb-6">
                                Нажмите кнопку ниже, чтобы присоединиться
                            </p>
                            <x-filament::button wire:click="joinSession" color="success" size="lg" class="w-full"
                                icon="heroicon-o-video-camera">
                                Присоединиться к занятию
                            </x-filament::button>
                        @else
                            {{-- Room not started --}}
                            <div
                                class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-amber-100 dark:bg-amber-900 mb-4">
                                <x-heroicon-o-clock class="h-10 w-10 text-amber-600 dark:text-amber-400 animate-pulse" />
                            </div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                                Занятие еще не началось
                            </h2>
                            <p class="text-gray-600 dark:text-gray-400 mb-6">
                                Ожидаем преподавателя...
                            </p>
                            <div class="flex justify-center gap-1 mb-4">
                                <span class="w-2 h-2 bg-indigo-500 rounded-full animate-bounce"
                                    style="animation-delay: 0s"></span>
                                <span class="w-2 h-2 bg-indigo-500 rounded-full animate-bounce"
                                    style="animation-delay: 0.2s"></span>
                                <span class="w-2 h-2 bg-indigo-500 rounded-full animate-bounce"
                                    style="animation-delay: 0.4s"></span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-500">
                                Страница обновится автоматически
                            </p>
                        @endif
                    </div>
                @else
                    {{-- Name Input State --}}
                    <div>
                        <div class="text-center mb-6">
                            <div
                                class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-indigo-100 dark:bg-indigo-900 mb-4">
                                <x-heroicon-o-user class="h-10 w-10 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                                Представьтесь
                            </h2>
                            <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">
                                Это имя увидят другие участники
                            </p>
                        </div>

                        <form wire:submit="submitName" class="space-y-6">
                            {{ $this->form }}

                            <x-filament::button type="submit" color="primary" size="lg" class="w-full"
                                icon="heroicon-o-arrow-right-on-rectangle">
                                Войти
                            </x-filament::button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        {{-- Back link --}}
        <div class="mt-4 text-center">
            <a href="/"
                class="text-sm text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                ← Вернуться на главную
            </a>
        </div>
    </div>
</div>