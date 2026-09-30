<x-mail::message>
<x-slot:preheader>Кабинет учителя готов — данные для входа в письме</x-slot:preheader>
# Заявка одобрена

{{ trim($user->first_name . ' ' . $user->middle_name) ?: 'Здравствуйте' }}, добро пожаловать в {{ \App\Support\Seo::SITE_NAME }}! Мы создали для вас кабинет учителя.

<x-mail::rows title="Данные для входа" :rows="[
    ['label' => 'Почта', 'value' => $user->email],
    ['label' => 'Пароль', 'value' => $password],
]" />

<x-mail::button :url="route('login')">
Войти в кабинет
</x-mail::button>

После первого входа смените пароль в профиле.
</x-mail::message>
