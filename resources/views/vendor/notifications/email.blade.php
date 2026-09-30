{{-- Письмо-уведомление (MailMessage): заголовок, строки, кнопка, строки после кнопки, подпись.
     Под линией — ссылка текстом на случай, если кнопка не открывается. --}}
<x-mail::message>
@if (! empty($introLines))
<x-slot:preheader>{{ \Illuminate\Support\Str::limit(strip_tags((string) $introLines[0]), 120) }}</x-slot:preheader>
@endif
@if (! empty($greeting))
# {{ $greeting }}
@elseif ($level === 'error')
# Что-то пошло не так
@else
# Здравствуйте!
@endif

@foreach ($introLines as $line)
{{ $line }}

@endforeach
@isset($actionText)
<x-mail::button :url="$actionUrl" :color="$level === 'error' ? 'dark' : 'primary'">
{{ $actionText }}
</x-mail::button>
@endisset
@foreach ($outroLines as $line)
{{ $line }}

@endforeach
@if (! empty($salutation))
<p class="muted">{{ $salutation }}</p>
@endif
@isset($actionText)
<x-slot:subcopy>
Кнопка не открывается? Скопируйте ссылку в адресную строку браузера: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
