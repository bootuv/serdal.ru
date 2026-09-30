{{-- Письмо Serdal: шапка с логотипом, тело, подвал с сайтом и почтой поддержки.
     Слот preheader — строка, которую почтовая программа показывает рядом с темой. --}}
@php
    $site = rtrim((string) config('app.url'), '/');
    $support = \App\Support\OfferSettings::legal()['legal_email'] ?? null;
@endphp
<x-mail::layout>
@isset($preheader)
<x-slot:preheader>{{ $preheader }}</x-slot:preheader>
@endisset
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="$site" :message="$message ?? null">
{{ \App\Support\Seo::SITE_NAME }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{ \App\Support\Seo::SITE_NAME }} · [{{ preg_replace('#^https?://#', '', $site) }}]({{ $site }})

@if ($support)
Вопросы — [{{ $support }}](mailto:{{ $support }}). Отвечать на это письмо не нужно.
@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
