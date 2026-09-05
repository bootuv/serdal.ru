{{--
    Постоянная ссылка на чат техподдержки в футере сайдбара (панели учителя и ученика).
    На десктопе всегда на виду внизу меню, на мобильных — внизу бургер-меню.
    Показывает счётчик непрочитанных ответов поддержки.
--}}
@php
    $user = auth()->user();
    $panelId = \Filament\Facades\Filament::getCurrentPanel()?->getId();

    $messengerRoute = match ($panelId) {
        'student' => 'filament.student.pages.messenger',
        'app' => 'filament.app.pages.messenger',
        default => null,
    };

    if (!$user || !$messengerRoute) {
        return;
    }

    $supportChat = \App\Models\SupportChat::where('user_id', $user->id)->first();
    $unread = $supportChat
        ? \App\Models\SupportMessage::where('support_chat_id', $supportChat->id)
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count()
        : 0;
@endphp

{{-- Без заливки: сайдбар на десктопе прозрачный, поэтому блок наследует фон страницы.
     Отступы и разделитель — как у групп навигации (px-6, линия внутри отступов). --}}
<div class="fi-sidebar-support shrink-0 px-6 pb-4">
    <div class="border-t border-gray-200 pt-4 dark:border-white/10">
        <a href="{{ route($messengerRoute, ['support' => 1]) }}"
            class="fi-sidebar-item-button relative -mx-2 flex items-center gap-x-3 rounded-lg px-2 py-2 text-sm font-medium text-gray-700 outline-none transition duration-75 hover:bg-gray-100 focus-visible:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5 dark:focus-visible:bg-white/5"
            title="Ошибки, вопросы и пожелания — напишите нам">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                class="h-6 w-6 shrink-0 text-gray-400 dark:text-gray-500">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
            </svg>
            <span class="flex-1 truncate">Чат техподдержки</span>
            {{-- Счётчик непрочитанных ответов поддержки — как у пунктов меню (стили в app.css) --}}
            @if($unread > 0)
                <span>
                    <x-filament::badge color="warning" :tooltip="plural_ru($unread, 'непрочитанный ответ', 'непрочитанных ответа', 'непрочитанных ответов') . ' техподдержки'">
                        {{ $unread }}
                    </x-filament::badge>
                </span>
            @endif
        </a>
    </div>
</div>
