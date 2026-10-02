<x-mail::message :unsubscribe="$unsubscribeUrl">
@if ($preheader)
<x-slot:preheader>{{ $preheader }}</x-slot:preheader>
@endif
{!! $bodyHtml !!}

@if ($buttonText && $buttonUrl)
<x-mail::button :url="$buttonUrl">
{{ $buttonText }}
</x-mail::button>
@endif
@if ($pixelUrl)
<img src="{{ $pixelUrl }}" width="1" height="1" alt="" class="pixel">
@endif
</x-mail::message>
