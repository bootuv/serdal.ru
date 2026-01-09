@php
    $user = auth()->user();
    $currentPanelId = \Filament\Facades\Filament::getCurrentPanel()->getId();

    $messengerRoute = match ($currentPanelId) {
        'student' => 'filament.student.pages.messenger',
        'app' => 'filament.app.pages.messenger', // 'tutor' path uses 'app' ID in provider
        default => null,
    };
@endphp

<x-filament::section>
    <div class="flex items-center gap-x-4">
        <div class="flex-1">
            <h2 class="text-lg font-bold text-gray-950 dark:text-white">
                Добро пожаловать, {{ $user->first_name }}! 👋
            </h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Заметили ошибку или есть пожелания?
                @if($messengerRoute)
                    <a href="{{ route($messengerRoute, ['support' => 1]) }}"
                        class="font-medium text-amber-600 hover:text-amber-500 dark:text-amber-500 dark:hover:text-amber-400 hover:underline">
                        Напишите в чат техподдержки Serdal
                    </a>
                @else
                    Напишите в чат техподдержки Serdal.
                @endif
            </p>
        </div>
    </div>
</x-filament::section>