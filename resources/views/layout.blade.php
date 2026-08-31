<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="utf-8">
  <title>@yield('title', 'Serdal')</title>
  <meta content="width=device-width, initial-scale=1" name="viewport">
  @yield('meta')
  <meta property="og:site_name" content="Serdal">
  <meta property="og:locale" content="ru_RU">
  <link href="/css/normalize.css" rel="stylesheet" type="text/css">
  <link href="/css/webflow.css" rel="stylesheet" type="text/css">
  <link href="/css/serdal-ru.webflow.css" rel="stylesheet" type="text/css">
  @yield('styles')
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin="anonymous">
  <script src="https://ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js" type="text/javascript"></script>
  <script
    type="text/javascript">WebFont.load({ google: { families: ["Inter:regular,500,600,italic:cyrillic,latin"] } });</script>
  <script
    type="text/javascript">!function (o, c) { var n = c.documentElement, t = " w-mod-"; n.className += t + "js", ("ontouchstart" in o || o.DocumentTouch && c instanceof DocumentTouch) && (n.className += t + "touch") }(window, document);</script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>
    .body {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    .footer-spacer {
      flex: 1 0 auto;
    }

    .footer {
      flex-direction: column;
      align-items: stretch;
      gap: 48px;
      padding: 56px 32px 32px;
    }

    .footer a {
      text-decoration: none;
    }

    .footer-top {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 48px 64px;
      max-width: 1216px;
      width: 100%;
      margin: 0 auto;
    }

    .footer-brand {
      max-width: 340px;
    }

    .footer-brand img {
      height: 32px;
    }

    .footer-brand p {
      margin: 16px 0 0;
      font-size: 15px;
      line-height: 1.55;
      color: #9b9e9e;
    }

    .footer-cols {
      display: flex;
      flex-wrap: wrap;
      gap: 40px 72px;
    }

    .footer-col {
      display: flex;
      flex-direction: column;
      gap: 12px;
      min-width: 160px;
    }

    .footer-col__title {
      margin-bottom: 4px;
      font-size: 13px;
      font-weight: 600;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: #fff;
      opacity: .55;
    }

    .footer-col a {
      font-size: 16px;
      color: #d6d8d8;
      transition: color .15s ease;
    }

    .footer-col a:hover {
      color: var(--brand-main, #ffe500);
    }

    .footer-bottom {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 8px 24px;
      max-width: 1216px;
      width: 100%;
      margin: 0 auto;
      padding-top: 24px;
      border-top: 1px solid rgba(255, 255, 255, .12);
      font-size: 14px;
      color: #9b9e9e;
    }

    @media (max-width: 767px) {
      .footer {
        padding: 40px 20px 28px;
      }

      .footer-cols {
        gap: 32px 48px;
      }
    }

    .star-rating {
      display: flex;
      justify-content: center;
      gap: 2px;
      margin-top: 12px;
    }

    .star-rating svg {
      flex-shrink: 0;
    }
  </style>
</head>

<body class="body" x-data="{ mobileMenuOpen: false }">
  <section
    class="header {{ Request::is('/') ? 'home' : (Route::currentRouteName() == 'tutors.show' ? 'tutor-page' : 'underline') }}">
    <a href="/" class="logo-wrapper w-inline-block"><img src="/images/Logo.svg" loading="lazy" width="Auto" height="32"
        alt="" class="logo"></a>
    <div class="menu-wrapper">
      <div class="main-menu">
        <a href="{{ route('about') }}" class="p24">О нас</a>
        <a href="{{ route('tariffs') }}" class="p24">Тарифы</a>
        <a href="{{ route('reviews') }}" class="p24">Отзывы</a>
        <a href="{{ route('help.index') }}" class="p24">Помощь</a>
        <a href="/login" class="p24">Войти</a>
      </div>
      <div @click="mobileMenuOpen = true" class="burger-menu-wrapper"><img src="/images/burger.svg" loading="lazy"
          width="32" height="32" alt="" class="burger-menu"></div>
    </div>
  </section>

  @yield('content')
  <div class="footer-spacer"></div>
  @php($legalRequisites = \App\Models\Setting::whereIn('key', ['legal_name', 'legal_inn', 'legal_ogrn'])->pluck('value', 'key'))
  <section class="footer">
    <div class="footer-top">
      <div class="footer-brand">
        <a href="/" class="w-inline-block"><img src="/images/Logo-white.svg" loading="lazy" height="32" alt="Serdal"></a>
        <p>Платформа для онлайн-занятий: виртуальные комнаты, расписание, домашние задания и учёт оплат — всё в одном
          месте.</p>
      </div>
      <div class="footer-cols">
        <div class="footer-col">
          <div class="footer-col__title">Платформа</div>
          <a href="{{ route('about') }}">О нас</a>
          <a href="{{ route('tariffs') }}">Тарифы</a>
          <a href="{{ route('reviews') }}">Отзывы</a>
          <a href="{{ route('help.index') }}">Помощь</a>
        </div>
        <div class="footer-col">
          <div class="footer-col__title">Документы</div>
          <a href="{{ route('privacy') }}">Конфиденциальность</a>
          <a href="{{ route('terms') }}">Условия использования</a>
          <a href="{{ route('offer') }}">Публичная оферта</a>
        </div>
        <div class="footer-col">
          <div class="footer-col__title">Контакты</div>
          <a href="mailto:info@serdal.ru">info@serdal.ru</a>
          <a href="/login">Войти в кабинет</a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <div>© {{ date('Y') }} Serdal</div>
      @if(!empty($legalRequisites['legal_name']))
        <div class="footer-bottom__legal">
          {{ $legalRequisites['legal_name'] }}
          @if(!empty($legalRequisites['legal_inn']))
            · ИНН {{ $legalRequisites['legal_inn'] }}
          @endif
          @if(!empty($legalRequisites['legal_ogrn']))
            · ОГРН/ОГРНИП {{ $legalRequisites['legal_ogrn'] }}
          @endif
        </div>
      @endif
    </div>
  </section>
  <div class="popup-wrapper auto-stopper">
    <div class="popup">
      <div style="position: relative; padding-top: 56.25%; width: 100%"><iframe
          src="https://kinescope.io/embed/wYafVzJKj2XZqfUvmAeM7H"
          allow="autoplay; fullscreen; picture-in-picture; encrypted-media; gyroscope; accelerometer; clipboard-write; screen-wake-lock;"
          frameborder="0" allowfullscreen
          style="position: absolute; width: 100%; height: 100%; top: 0; left: 0;"></iframe></div>
    </div>
    <div class="close-button">Закрыть</div>
    <div data-w-id="43e43230-fe7b-3950-1162-acc7201c2860" class="close-click-zone"></div>
  </div>
  <div class="mobile-menu-wrapper" :class="{ 'open': mobileMenuOpen }">
    <div @click="mobileMenuOpen = false" class="mobile-menu-close-zone"></div>
    <div class="mobile-menu">
      <div @click="mobileMenuOpen = false" class="menu-close"><img src="/images/close.svg" loading="lazy" alt=""></div>
      <div class="mobile-menu-litems">
        <a href="{{ route('about') }}" class="p30">О нас</a>
        <a href="{{ route('tariffs') }}" class="p30">Тарифы</a>
        <a href="{{ route('reviews') }}" class="p30">Отзывы</a>
        <a href="{{ route('help.index') }}" class="p30">Помощь</a>
        <a href="/login" class="p30">Войти</a>
      </div>
    </div>
    <div class="mobile-menu-bg"></div>
  </div>
  <script src="https://d3e54v103j8qbb.cloudfront.net/js/jquery-3.5.1.min.dc5e7f18c8.js?site=668d4be20ae6c2fa4bba833d"
    type="text/javascript" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0="
    crossorigin="anonymous"></script>
  <script src="/js/webflow.js" type="text/javascript"></script>
  <script src="/js/specialists-filter.js" type="text/javascript"></script>
  <script>
    var autoStopperBtn = document.querySelectorAll('.auto-stopper');
    for (var i = 0; i < autoStopperBtn.length; i++) {
      (function (i) {
        autoStopperBtn[i].onclick = function () {
          var autoStopperModule = autoStopperBtn[i].closest('.auto-stopper');
          var autoStopperFrame = autoStopperModule.querySelector('iframe');
          var autoStopperSrc = autoStopperFrame.src;
          autoStopperFrame.src = '';
          autoStopperFrame.src = autoStopperSrc;
        }
      }(i));
    }
  </script>
  <!-- Yandex.Metrika counter -->
  <script type="text/javascript">
    (function (m, e, t, r, i, k, a) {
      m[i] = m[i] || function () { (m[i].a = m[i].a || []).push(arguments) };
      m[i].l = 1 * new Date();
      for (var j = 0; j < document.scripts.length; j++) { if (document.scripts[j].src === r) { return; } }
      k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a)
    })
      (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

    ym(94765186, "init", {
      clickmap: true,
      trackLinks: true,
      accurateTrackBounce: true,
      webvisor: true
    });
  </script>
  <noscript>
    <div><img src="https://mc.yandex.ru/watch/94765186" style="position:absolute; left:-9999px;" alt="" /></div>
  </noscript>
  <!-- /Yandex.Metrika counter -->
</body>

</html>