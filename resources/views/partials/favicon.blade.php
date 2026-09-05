{{-- Favicon: SVG адаптируется под тему (тёмный в светлой, белый в тёмной), ICO — фолбэк для старых браузеров.
     Иконка для главного экрана телефона настраивается в админке («Настройки» → SEO) --}}
<link rel="icon" href="{{ asset('images/favicon.ico') }}?v=2" sizes="32x32">
<link rel="icon" type="image/svg+xml" href="{{ asset('images/favicon.svg') }}?v=2">
<link rel="apple-touch-icon" href="{{ \App\Support\SeoSettings::fileUrl('seo_apple_touch_icon') }}{{ \App\Support\SeoSettings::get('seo_apple_touch_icon') === '' ? '?v=2' : '' }}">
