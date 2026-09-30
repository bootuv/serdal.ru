@php($purpose ??= 'register')
<x-mail::message>
<x-slot:preheader>Код подтверждения: {{ $code }}</x-slot:preheader>
@if ($purpose === 'email')
# Подтвердите новую почту

Введите этот код в кабинете — после этого входить нужно будет с этой почтой.
@elseif ($purpose === 'password')
# Подтвердите смену пароля

Введите этот код в кабинете, чтобы задать новый пароль.
@else
# Подтвердите почту

Введите этот код на странице регистрации.
@endif

<x-mail::highlight :value="$code" code note="Действует 30 минут" />

@if ($purpose === 'email')
Если вы не меняли почту в {{ \App\Support\Seo::SITE_NAME }}, просто не отвечайте на это письмо.
@elseif ($purpose === 'password')
Если вы не меняли пароль, никому не называйте этот код и напишите в поддержку — адрес внизу письма.
@else
Если вы не регистрировались в {{ \App\Support\Seo::SITE_NAME }}, просто не отвечайте на это письмо.
@endif
</x-mail::message>
