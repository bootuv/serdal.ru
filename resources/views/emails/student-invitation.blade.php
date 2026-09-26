@component('mail::message')
# Приглашение от учителя

Здравствуйте! {{ $teacherName }} приглашает вас заниматься в {{ \App\Support\Seo::SITE_NAME }}.

@component('mail::button', ['url' => $link])
Принять приглашение
@endcomponent

Нет аккаунта — создадите его за минуту. Уже есть — просто войдите, и учитель появится в вашем кабинете.
@endcomponent
