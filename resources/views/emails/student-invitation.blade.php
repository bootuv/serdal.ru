<x-mail::message>
    # Приглашение

    Вас пригласили зарегистрироваться в системе.

    <x-mail::button :url="$link">
        Принять приглашение
    </x-mail::button>

    Спасибо,<br>
    {{ config('app.name') }}
</x-mail::message>