@component('mail::message')
# Приглашение

Преподаватель **{{ $teacherName }}** пригласил Вас присоединиться к платформе.

@component('mail::button', ['url' => $link])
Принять приглашение
@endcomponent

Спасибо,<br>
{{ config('app.name') }}
@endcomponent