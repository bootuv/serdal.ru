<!DOCTYPE html>
<html data-wf-page="668d4be20ae6c2fa4bba8346" data-wf-site="668d4be20ae6c2fa4bba833d" lang="ru">
<head>
  <meta charset="utf-8">
  <title>Serdal</title>
  <meta content="width=device-width, initial-scale=1" name="viewport">
  <meta content="Webflow" name="generator">
  <link href="css/normalize.css" rel="stylesheet" type="text/css">
  <link href="css/webflow.css" rel="stylesheet" type="text/css">
  <link href="css/serdal-ru.webflow.css" rel="stylesheet" type="text/css">
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin="anonymous">
  <script src="https://ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js" type="text/javascript"></script>
  <script type="text/javascript">WebFont.load({  google: {    families: ["Inter:regular,500,600,italic:cyrillic,latin"]  }});</script>
  <script type="text/javascript">!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
  <link href="images/favicon.png" rel="shortcut icon" type="image/x-icon">
  <link href="images/webclip.png" rel="apple-touch-icon">
  <style>
    .filters-wrapper, .tabs {
      -ms-overflow-style: none;
      scrollbar-width: none;
      overflow-x: scroll;
    }
    .filters-wrapper::-webkit-scrollbar, .tabs::-webkit-scrollbar {
      display: none;
    }
    @keyframes ticker {
      0% { transform: translateX(0); }
      100% { transform: translateX(-50%); }
    }
    .directions-row {
      overflow: hidden;
    }
    .directions {
      display: flex;
      width: fit-content;
      animation: ticker 20s linear infinite;
      -webkit-transform-style: preserve-3d;
    }
    .direction {
      -webkit-backface-visibility: hidden;
    }
  </style>
</head>
<body class="body">
  @yield('content')
  <section class="footer">
    <div class="p18 copyright">© 2024 Serdal</div>
    <div class="footer-menu">
      <a href="#" class="white-text p18">О нас</a>
      <a href="#" class="white-text p18">Отзывы</a>
      <a href="#" class="white-text p18">Контакты</a>
    </div>
  </section>
  <div class="popup-wrapper auto-stopper">
    <div class="popup">
      <div style="padding-top:56.27659574468085%" class="video w-video w-embed"><iframe class="embedly-embed" src="https://cdn.embedly.com/widgets/media.html?src=https%3A%2F%2Fplayer.vimeo.com%2Fvideo%2F1007718657%3Fapp_id%3D122963&dntp=1&display_name=Vimeo&url=https%3A%2F%2Fvimeo.com%2F1007718657&image=https%3A%2F%2Fi.vimeocdn.com%2Fvideo%2F1924634575-d0cb03e50ef9708d7c52b0af022f099fa4593fc366fe3db58eadc9ac85e2ff8d-d_1280&key=c4e54deccf4d4ec997a64902e9a30300&type=text%2Fhtml&schema=vimeo" width="940" height="529" scrolling="no" allowfullscreen="" title="The Game"></iframe></div>
    </div>
    <div class="close-button">Закрыть</div>
    <div data-w-id="43e43230-fe7b-3950-1162-acc7201c2860" class="close-click-zone"></div>
  </div>
  <div class="mobile-menu-wrapper">
    <div data-w-id="f715a21e-d358-986f-0868-b5b89a81cdc7" class="mobile-menu-close-zone"></div>
    <div style="-webkit-transform:translate3d(300px, 0, 0) scale3d(1, 1, 1) rotateX(0) rotateY(0) rotateZ(0) skew(0, 0);-moz-transform:translate3d(300px, 0, 0) scale3d(1, 1, 1) rotateX(0) rotateY(0) rotateZ(0) skew(0, 0);-ms-transform:translate3d(300px, 0, 0) scale3d(1, 1, 1) rotateX(0) rotateY(0) rotateZ(0) skew(0, 0);transform:translate3d(300px, 0, 0) scale3d(1, 1, 1) rotateX(0) rotateY(0) rotateZ(0) skew(0, 0)" class="mobile-menu">
      <div data-w-id="f715a21e-d358-986f-0868-b5b89a81cdc9" class="menu-close"><img src="images/close.svg" loading="lazy" alt=""></div>
      <div class="mobile-menu-litems">
        <a href="#" target="_blank" class="p30">О нас</a>
        <a href="reviews.html" class="p30">Отзывы</a>
        <a href="https://room.serdal.ru/signin" target="_blank" class="p30">Войти</a>
      </div>
    </div>
    <div style="opacity:0" class="mobile-menu-bg"></div>
  </div>
    <script src="https://d3e54v103j8qbb.cloudfront.net/js/jquery-3.5.1.min.dc5e7f18c8.js?site=668d4be20ae6c2fa4bba833d" type="text/javascript" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
    <script src="js/webflow.js" type="text/javascript"></script>
    <script>
        var autoStopperBtn      =   document.querySelectorAll('.auto-stopper');
        for (var i = 0; i < autoStopperBtn.length; i++) {
            (function(i) {
                autoStopperBtn[i].onclick = function () {
                var autoStopperModule   =   autoStopperBtn[i].closest('.auto-stopper');
                var autoStopperFrame    =   autoStopperModule.querySelector('iframe');
                var autoStopperSrc      =   autoStopperFrame.src;
                autoStopperFrame.src = '';
                autoStopperFrame.src = autoStopperSrc;
                }
            }(i));
        }
    </script>
</body>
</html>