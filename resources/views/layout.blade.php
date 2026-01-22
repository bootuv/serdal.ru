<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="utf-8">
  <title>@yield('title', 'Serdal')</title>
  <meta content="width=device-width, initial-scale=1" name="viewport">
  @yield('meta')
  <meta property="og:site_name" content="Serdal">
  <meta property="og:locale" content="ru_RU">
  <link href="css/normalize.css" rel="stylesheet" type="text/css">
  <link href="css/webflow.css" rel="stylesheet" type="text/css">
  <link href="css/serdal-ru.webflow.css" rel="stylesheet" type="text/css">
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin="anonymous">
  <script src="https://ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js" type="text/javascript"></script>
  <script
    type="text/javascript">WebFont.load({ google: { families: ["Inter:regular,500,600,italic:cyrillic,latin"] } });</script>
  <script
    type="text/javascript">!function (o, c) { var n = c.documentElement, t = " w-mod-"; n.className += t + "js", ("ontouchstart" in o || o.DocumentTouch && c instanceof DocumentTouch) && (n.className += t + "touch") }(window, document);</script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="body" x-data="{ mobileMenuOpen: false }">
  <section
    class="header {{ Request::is('/') ? 'home' : (Route::currentRouteName() == 'tutors.show' ? 'tutor-page' : 'underline') }}">
    <a href="/" class="logo-wrapper w-inline-block"><img src="/images/Logo.svg" loading="lazy" width="Auto" height="32"
        alt="" class="logo"></a>
    <div class="menu-wrapper">
      <div class="main-menu">
        <a href="#" target="_blank" class="p24">О нас</a>
        <a href="{{ route('reviews') }}" class="p24">Отзывы</a>
        <a href="/welcome" target="_blank" class="p24">Войти</a>
      </div>
      <div @click="mobileMenuOpen = true" class="burger-menu-wrapper"><img src="/images/burger.svg" loading="lazy"
          width="32" height="32" alt="" class="burger-menu"></div>
    </div>
  </section>

  @yield('content')
  <section class="footer">
    <div class="p18 copyright">© {{ date('Y') }} Serdal</div>
    <div class="footer-menu">
      <a href="#" class="white-text p18">О нас</a>
      <a href="{{ route('reviews') }}" class="white-text p18">Отзывы</a>
      <a href="{{ route('privacy') }}" class="white-text p18">Конфиденциальность</a>
      <a href="{{ route('terms') }}" class="white-text p18">Условия</a>
      <a href="mailto:info@serdal.ru" class="white-text p18">info@serdal.ru</a>
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
        <a href="#" target="_blank" class="p30">О нас</a>
        <a href="{{ route('reviews') }}" class="p30">Отзывы</a>
        <a href="/welcome" target="_blank" class="p30">Войти</a>
      </div>
    </div>
    <div class="mobile-menu-bg"></div>
  </div>
  <script src="https://d3e54v103j8qbb.cloudfront.net/js/jquery-3.5.1.min.dc5e7f18c8.js?site=668d4be20ae6c2fa4bba833d"
    type="text/javascript" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0="
    crossorigin="anonymous"></script>
  <script src="js/webflow.js" type="text/javascript"></script>
  <script src="js/specialists-filter.js" type="text/javascript"></script>
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