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
    .filters-wrapper {
      -ms-overflow-style: none;
      scrollbar-width: none;
      overflow-x: scroll;
    }
    .filters-wrapper::-webkit-scrollbar {
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