<x-mail::message>
<x-slot:preheader>Примите приглашение — учитель появится в вашем кабинете</x-slot:preheader>
# {{ $teacherName }} приглашает вас заниматься

Занятия проходят онлайн в {{ \App\Support\Seo::SITE_NAME }}: расписание, задания и материалы — в вашем кабинете.

<x-mail::button :url="$link">
Принять приглашение
</x-mail::button>

Нет аккаунта — создадите его за минуту. Уже есть — просто войдите, и учитель появится в вашем кабинете.

<x-slot:subcopy>
Кнопка не открывается? Скопируйте ссылку в адресную строку браузера: <span class="break-all">[{{ $link }}]({{ $link }})</span>
</x-slot:subcopy>
</x-mail::message>
