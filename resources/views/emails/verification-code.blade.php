<x-mail::message>
<x-slot:preheader>Код подтверждения: {{ $code }}</x-slot:preheader>
# Подтвердите почту

Введите этот код на странице регистрации.

<x-mail::highlight :value="$code" code note="Действует 30 минут" />

Если вы не регистрировались в {{ \App\Support\Seo::SITE_NAME }}, просто не отвечайте на это письмо.
</x-mail::message>
