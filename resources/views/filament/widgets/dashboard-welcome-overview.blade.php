@php
    $user = auth()->user();
    // Ссылка на чат техподдержки — в футере сайдбара и в меню под аватаром (см. support-link)
    $name = trim((string) $user->first_name) !== '' ? $user->first_name : $user->name;
@endphp

<x-filament::section>
    <div class="flex items-center gap-x-4">
        <div class="flex-1">
            <h2 class="text-lg font-bold text-gray-950 dark:text-white">
                Добро пожаловать, {{ $name }}! 👋
            </h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Здесь собраны ваши ближайшие занятия и домашние задания.
            </p>
        </div>
    </div>
</x-filament::section>
