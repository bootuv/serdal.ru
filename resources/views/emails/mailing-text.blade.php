{!! $plainBody !!}
@if ($buttonText && $buttonUrl)

{{ $buttonText }}: {{ $buttonUrl }}
@endif

—
{{ \App\Support\Seo::SITE_NAME }} · {{ rtrim((string) config('app.url'), '/') }}
Отписаться от рассылки: {{ $unsubscribeUrl }}
