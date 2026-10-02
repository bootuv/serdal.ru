<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="utf-8">
  @php
    $seoTitle = trim($__env->yieldContent('title', \App\Support\Seo::defaultTitle()));
    $seoDescription = \App\Support\Seo::text($__env->yieldContent('description', \App\Support\Seo::defaultDescription()), 300);
    $seoCanonical = trim($__env->yieldContent('canonical', \App\Support\Seo::canonical()));
    $seoImage = trim($__env->yieldContent('og_image', \App\Support\Seo::defaultImage()));
    $seoType = trim($__env->yieldContent('og_type', 'website'));
    $seoRobots = \App\Support\Seo::robots(trim($__env->yieldContent('robots', '')));
    $seoSiteName = \App\Support\Seo::siteName();
  @endphp
  <title>{{ $seoTitle }}</title>
  <meta content="width=device-width, initial-scale=1" name="viewport">
  <meta name="description" content="{{ $seoDescription }}">
  <meta name="robots" content="{{ $seoRobots }}">
  <link rel="canonical" href="{{ $seoCanonical }}">
  <meta property="og:site_name" content="{{ $seoSiteName }}">
  <meta property="og:locale" content="ru_RU">
  <meta property="og:type" content="{{ $seoType }}">
  <meta property="og:title" content="{{ $seoTitle }}">
  <meta property="og:description" content="{{ $seoDescription }}">
  <meta property="og:url" content="{{ $seoCanonical }}">
  <meta property="og:image" content="{{ $seoImage }}">
  <meta property="og:image:alt" content="{{ $seoTitle }}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $seoTitle }}">
  <meta name="twitter:description" content="{{ $seoDescription }}">
  <meta name="twitter:image" content="{{ $seoImage }}">
  @yield('meta')
  @include('partials.favicon')
  <meta name="theme-color" content="#ffe500">
  @if(\App\Support\SeoSettings::get('seo_yandex_verification') !== '')
  <meta name="yandex-verification" content="{{ \App\Support\SeoSettings::get('seo_yandex_verification') }}">
  @endif
  @if(\App\Support\SeoSettings::get('seo_google_verification') !== '')
  <meta name="google-site-verification" content="{{ \App\Support\SeoSettings::get('seo_google_verification') }}">
  @endif
  {!! \App\Support\Seo::jsonLd(\App\Support\Seo::organization()) !!}
  {!! \App\Support\Seo::jsonLd(\App\Support\Seo::website()) !!}
  @stack('jsonld')
  {!! \App\Support\SeoSettings::get('seo_head_extra') !!}
  <link href="/css/normalize.css" rel="stylesheet" type="text/css">
  <link href="/css/webflow.css" rel="stylesheet" type="text/css">
  <link href="/css/serdal-ru.webflow.css?v={{ filemtime(public_path('css/serdal-ru.webflow.css')) }}" rel="stylesheet" type="text/css">
  <link href="/css/catalog.css?v={{ filemtime(public_path('css/catalog.css')) }}" rel="stylesheet" type="text/css">
  @yield('styles')
  {{-- Шрифт — обычной таблицей стилей с display=swap: текст виден сразу, без блокирующего загрузчика --}}
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin="anonymous">
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;1,400&amp;display=swap" rel="stylesheet">
  <script
    type="text/javascript">!function (o, c) { var n = c.documentElement, t = " w-mod-"; n.className += t + "js", ("ontouchstart" in o || o.DocumentTouch && c instanceof DocumentTouch) && (n.className += t + "touch") }(window, document);</script>
  {{-- Страницы с компонентами Livewire (секция livewire) получают Alpine вместе с Livewire — вторая копия ломает обе --}}
  @unless(View::hasSection('livewire'))
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  @endunless
  <style>
    .body {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    .footer-spacer {
      flex: 1 0 auto;
    }

    /* Подвал — на всю ширину, поля как у шапки (32 / 24 / 16px); слева описание, справа колонки ссылок одним рядом */
    .footer {
      flex-direction: column;
      align-items: stretch;
      gap: 48px;
      padding: 64px 32px 32px;
    }

    .footer a {
      text-decoration: none;
    }

    .footer-top {
      display: grid;
      grid-template-columns: minmax(0, 4fr) minmax(0, 8fr);
      gap: 48px 96px;
    }

    .footer-brand img {
      height: 32px;
    }

    .footer-brand p {
      max-width: 520px;
      margin: 24px 0 0;
      font-size: 16px;
      line-height: 1.6;
      color: #9b9e9e;
    }

    .footer-cols {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 40px 32px;
    }

    .footer-col {
      display: flex;
      flex-direction: column;
      gap: 12px;
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
      line-height: 22px;
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
      padding-top: 24px;
      border-top: 1px solid rgba(255, 255, 255, .12);
      font-size: 14px;
      color: #9b9e9e;
    }

    @media (max-width: 991px) {
      .footer {
        padding: 56px 24px 32px;
      }

      .footer-top {
        grid-template-columns: minmax(0, 1fr);
      }

      .footer-brand p {
        max-width: 560px;
      }
    }

    @media (max-width: 767px) {
      /* На телефоне 100px над подвалом — пустой экран; 64px хватает, чтобы отделить его от страницы */
      .footer {
        margin-top: 64px;
        padding: 40px 24px 28px;
      }

      .footer-cols {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 32px 24px;
      }
    }

    @media (max-width: 479px) {
      .footer {
        padding-left: 16px;
        padding-right: 16px;
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

    /* Вошедший пользователь в шапке: аватар и меню, на одной линии с пунктами меню */
    [x-cloak] { display: none !important; }

    .main-menu,
    .menu-wrapper {
      align-items: center;
    }

    .site-user {
      position: relative;
      display: flex;
      align-items: center;
    }

    /* Аватар 44px выше строки меню (32px): отрицательные поля — шапка не растет после входа и не наезжает на обложку */
    .site-user-toggle {
      display: flex;
      margin: -6px 0;
      padding: 0;
      border: 0;
      border-radius: 14px;
      background: none;
      cursor: pointer;
      transition: box-shadow .15s;
    }

    .site-user-toggle:hover,
    .site-user-toggle[aria-expanded="true"] {
      box-shadow: 0 0 0 3px var(--bg2);
    }

    .site-user-pic {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      object-fit: cover;
    }

    .site-user-menu {
      position: absolute;
      top: calc(100% + 8px);
      right: 0;
      z-index: 50;
      display: flex;
      flex-flow: column;
      min-width: 240px;
      padding: 6px;
      border: 1px solid var(--line-light);
      border-radius: 16px;
      background: var(--white);
      box-shadow: 0 12px 32px #2322151a;
    }

    .site-user-head {
      display: flex;
      flex-flow: column;
      gap: 2px;
      margin-bottom: 4px;
      padding: 10px 12px 12px;
      border-bottom: 1px solid var(--line-light);
    }

    .site-user-name {
      color: var(--black);
      font-size: 16px;
      font-weight: 600;
      line-height: 22px;
    }

    .site-user-role {
      color: var(--gray);
      font-size: 14px;
      line-height: 20px;
    }

    .site-user-menu a,
    .site-user-menu button {
      display: block;
      width: 100%;
      padding: 10px 12px;
      border: 0;
      border-radius: 10px;
      background: none;
      color: var(--black);
      font: inherit;
      font-size: 16px;
      line-height: 22px;
      text-align: left;
      text-decoration: none;
      cursor: pointer;
    }

    .site-user-menu a:hover,
    .site-user-menu button:hover {
      background-color: var(--bg1);
    }

    .site-user-menu form {
      margin: 4px 0 0;
      padding-top: 4px;
      border-top: 1px solid var(--line-light);
    }

    .site-user-mobile {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 20px;
      font-weight: 600;
    }

    .site-user-logout {
      margin: 0;
    }

    .site-user-logout button {
      padding: 0;
      border: 0;
      background: none;
      color: inherit;
      cursor: pointer;
    }
  </style>
</head>

<body class="body" x-data="{ mobileMenuOpen: false }">
  @php
    // Вошедший пользователь: вместо «Войти» — аватар и меню (кабинет, профиль, блог учителя, выход)
    $siteUser = auth()->user();
    $siteUserMenu = [];
    if ($siteUser) {
        $role = $siteUser->role;
        $cabinetUrl = \App\Http\Middleware\EnsureCabinetRole::homeFor($siteUser);
        $siteUserMenu[] = ['Кабинет', $cabinetUrl];
        $profileRoute = match ($role) {
            \App\Models\User::ROLE_STUDENT => 'cabinet.student.profile',
            \App\Models\User::ROLE_TUTOR => 'cabinet.teacher.profile',
            default => null,
        };
        if ($profileRoute && Route::has($profileRoute)) {
            $siteUserMenu[] = ['Профиль', route($profileRoute)];
        }
        if ($role === \App\Models\User::ROLE_TUTOR && $siteUser->username) {
            $siteUserMenu[] = ['Моя страница', route('tutors.show', $siteUser)];
        }
        if ($role === \App\Models\User::ROLE_TUTOR && Route::has('cabinet.teacher.blog')) {
            $siteUserMenu[] = ['Мои статьи', route('cabinet.teacher.blog')];
        }
        if ($role === \App\Models\User::ROLE_ADMIN && Route::has('cabinet.admin.blog')) {
            $siteUserMenu[] = ['Блог в админке', route('cabinet.admin.blog')];
        }
        $siteUserRole = match ($role) { \App\Models\User::ROLE_TUTOR => 'Учитель', \App\Models\User::ROLE_STUDENT => 'Ученик', \App\Models\User::ROLE_ADMIN => 'Администратор', default => '' };
    }
  @endphp
  <section
    class="header {{ Request::is('/') ? 'home' : (Route::currentRouteName() == 'tutors.show' ? 'tutor-page' : 'underline') }}">
    <a href="/" class="logo-wrapper w-inline-block"><img src="/images/Logo.svg" width="Auto" height="32"
        alt="{{ $seoSiteName }}" class="logo"></a>
    <div class="menu-wrapper">
      <div class="main-menu">
        <a href="{{ route('about') }}" class="p24">О нас</a>
        <a href="{{ route('tariffs') }}" class="p24">Тарифы</a>
        <a href="{{ route('reviews') }}" class="p24">Отзывы</a>
        <a href="{{ route('blog.index') }}" class="p24">Блог</a>
        <a href="{{ route('help.index') }}" class="p24">Помощь</a>
        @if($siteUser)
          <div class="site-user" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
            <button type="button" class="site-user-toggle" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="menu" aria-label="Меню профиля">
              @include('partials.userpic', ['user' => $siteUser, 'class' => 'site-user-pic'])
            </button>
            <div class="site-user-menu" x-show="open" x-cloak role="menu">
              <div class="site-user-head">
                <span class="site-user-name">{{ $siteUser->name }}</span>
                @if($siteUserRole)<span class="site-user-role">{{ $siteUserRole }}</span>@endif
              </div>
              @foreach($siteUserMenu as [$label, $href])
                <a href="{{ $href }}" role="menuitem">{{ $label }}</a>
              @endforeach
              <form method="POST" action="/logout">
                @csrf
                <button type="submit" role="menuitem">Выйти</button>
              </form>
            </div>
          </div>
        @else
          <a href="/login" class="p24">Войти</a>
        @endif
      </div>
      <div @click="mobileMenuOpen = true" class="burger-menu-wrapper"><img src="/images/burger.svg" loading="lazy"
          width="32" height="32" alt="" class="burger-menu"></div>
    </div>
  </section>

  @yield('content')
  <div class="footer-spacer"></div>
  @php($legalRequisites = \App\Support\OfferSettings::legal())
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
          <a href="{{ route('blog.index') }}">Блог</a>
          <a href="{{ route('help.index') }}">Помощь</a>
        </div>
        @php($footerSubjects = app(\App\Services\TutorCatalogService::class)->topSubjects(6))
        @if($footerSubjects)
          <div class="footer-col">
            <div class="footer-col__title">Репетиторы</div>
            @foreach($footerSubjects as $footerSubject)
              <a href="{{ $footerSubject['url'] }}">{{ $footerSubject['name'] }}</a>
            @endforeach
            <a href="{{ route('catalog.index') }}">Все предметы</a>
          </div>
        @endif
        <div class="footer-col">
          <div class="footer-col__title">Документы</div>
          <a href="{{ route('privacy') }}">Конфиденциальность</a>
          <a href="{{ route('terms') }}">Условия использования</a>
          <a href="{{ route('offer') }}">Публичная оферта</a>
        </div>
        <div class="footer-col">
          <div class="footer-col__title">Контакты</div>
          <a href="mailto:{{ $legalRequisites['legal_email'] }}">{{ $legalRequisites['legal_email'] }}</a>
          @if($siteUser)
            <a href="{{ \App\Http\Middleware\EnsureCabinetRole::homeFor($siteUser) }}">Перейти в кабинет</a>
          @else
            <a href="/login">Войти в кабинет</a>
          @endif
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
        <a href="{{ route('blog.index') }}" class="p30">Блог</a>
        <a href="{{ route('help.index') }}" class="p30">Помощь</a>
        @if($siteUser)
          <div class="site-user-mobile">
            @include('partials.userpic', ['user' => $siteUser, 'class' => 'site-user-pic'])
            <span>{{ $siteUser->name }}</span>
          </div>
          @foreach($siteUserMenu as [$label, $href])
            <a href="{{ $href }}" class="p30">{{ $label }}</a>
          @endforeach
          <form method="POST" action="/logout" class="site-user-logout">
            @csrf
            <button type="submit" class="p30">Выйти</button>
          </form>
        @else
          <a href="/login" class="p30">Войти</a>
        @endif
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