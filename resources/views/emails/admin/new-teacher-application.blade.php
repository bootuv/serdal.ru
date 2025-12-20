<x-mail::message>
    # Новая заявка на преподавателя

    **Имя:** {{ $application->full_name }}

    **Email:** {{ $application->email }}

    **Телефон:** {{ $application->phone }}

    <x-mail::button :url="url('/admin/teacher-applications')">
        Посмотреть заявки
    </x-mail::button>

    С уважением,
    {{ config('app.name') }}
</x-mail::message>