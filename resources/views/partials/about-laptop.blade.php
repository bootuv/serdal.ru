{{--
    Ноутбук на главном экране «О платформе».

    Сделан на CSS 3D, а не на WebGL: экран — обычный iframe, поэтому по кабинету внутри можно кликать.
    Крышка раскрывается по мере прокрутки (скрипт внизу задаёт трансформации), после раскрытия
    секция отпускает ноутбук и он уезжает вместе со страницей.

    На экране — демо-кабинет учителя (/demo/teacher, App\Demo): настоящие экраны кабинета с выдуманными данными.
    Он рисуется в «родном» размере 1440×900 (раскладка компьютера) и уменьшается zoom под ширину экрана.
    Стили — public/css/about-laptop.css.
--}}


<section class="lp" x-data="aboutLaptop" aria-label="Кабинет учителя Serdal">
    <div class="lp__sticky" x-ref="sticky">
        <div class="lp__scene" x-ref="scene">
            <div class="lp__lid" x-ref="lid">
                {{-- Внутренняя сторона крышки: рамка и экран --}}
                <div class="lp__face lp__face--front">
                    <span class="lp__camera" aria-hidden="true"></span>
                    <div class="lp__screen" x-ref="screen">

                        {{-- Пока крышка не раскрыта, экран не кликается (inert); src — сразу, чтобы к раскрытию кабинет уже загрузился --}}
                        <iframe class="lp__frame" x-ref="demo" src="{{ url('/demo/teacher') }}" title="Демо кабинета учителя Serdal"
                            loading="lazy" x-effect="$el.parentElement.inert = !ready"></iframe>

                    </div>
                </div>
                <div class="lp__lid-edge" aria-hidden="true"></div>
                {{-- Наружная сторона крышки — её видно, пока ноутбук закрыт --}}
                <div class="lp__face lp__face--back" aria-hidden="true">
                    {{-- Хромированный логотип: форма — маска из Logo.svg, металл — градиент с бликом (--sheen) --}}
                    <span class="lp__logo"><span class="lp__chrome" x-ref="logo"></span></span>
                </div>
            </div>

            <div class="lp__base" aria-hidden="true">
                <div class="lp__deck">
                    <div class="lp__keys">
                        @for ($i = 0; $i < 70; $i++)<i></i>@endfor
                    </div>
                    <div class="lp__pad"></div>
                </div>
                <div class="lp__edge"></div>
                <div class="lp__shadow"></div>
            </div>
        </div>

        {{-- Кот на закрытой крышке (в стиле чёрного силуэта): виляет хвостом, моргает, следит за курсором.
             Начали прокручивать — вздрагивает и спрыгивает, потом крышка раскрывается; вернулись наверх —
             крышка закрывается и кот запрыгивает обратно. Анимация — public/js/about-cat.js (GSAP).
             Части персонажа — отдельными группами со своими шарнирами. --}}
        <div class="lp__cat" x-ref="cat" aria-hidden="true">
            <svg viewBox="0 0 120 170" overflow="visible">
                <ellipse class="cat-shadow" cx="64" cy="166" rx="42" ry="5.5" fill="#202323" opacity=".16" />
                <g class="cat">
                    <g class="cat-tail">
                        <path d="M38 160 C18 162 6 148 6 131 C6 116 15 105 26 104 C33 104 35 111 30 117 C23 125 21 136 27 146 C31 153 38 156 44 156 Z" fill="#202323" />
                    </g>
                    <g class="cat-body">
                        <path d="M36 163 C29 132 30 102 39 80 C47 68 75 68 83 80 C92 102 93 132 86 163 Z" fill="#202323" />
                        <ellipse cx="44" cy="163" rx="11" ry="5" fill="#202323" />
                        <ellipse cx="57" cy="165" rx="6.5" ry="4" fill="#202323" />
                        <ellipse cx="83" cy="163" rx="9" ry="5" fill="#202323" />
                        {{-- Лапы и бедро — тонкими просветами цвета крышки --}}
                        <path d="M53 120 C56 136 57 151 55.5 164 M38 130 C47 131 53 139 53 151" stroke="#d4e5e4" stroke-width="1.8" stroke-linecap="round" fill="none" />
                    </g>
                    {{-- Передняя правая лапа — отдельно: ловит курсор и шлёпает (about-cat.js), шарнир в плече --}}
                    <g class="cat-paw">
                        {{-- В покое — ровно прежняя ступня (овал). Верх лапы уходит глубоко в силуэт тела: при повороте в плече
                             у плеча не открывается просвет --}}
                        <path d="M62 100 C61.5 120 61.5 140 62 152 C62.3 157 62.6 160 63 162 L73 162 C74 150 74 136 73.5 120 C73.5 110 73 104 72.5 100 Z" fill="#202323" />
                        <ellipse cx="68" cy="165" rx="6.5" ry="4" fill="#202323" />
                        {{-- Просвет между передними лапами — на самой лапе: в покое как раньше, при ударе едет вместе с ней --}}
                        <path d="M67 120 C64 136 63 151 64.5 164" stroke="#d4e5e4" stroke-width="1.8" stroke-linecap="round" fill="none" />
                    </g>
                    <g class="cat-head">
                        <g class="cat-ear cat-ear--l">
                            <path d="M31 52 L35 12 L54 34 Z" fill="#202323" />
                            <path d="M37 21 L39 35 L45 31 Z" fill="#fff" />
                        </g>
                        <g class="cat-ear cat-ear--r">
                            <path d="M89 50 L87 8 L66 33 Z" fill="#202323" />
                            <path d="M84 18 L82 32 L77 29 Z" fill="#fff" />
                        </g>
                        <path d="M29 64 C27 42 38 29 60 29 C82 29 93 42 91 64 C90 79 80 88 60 88 C40 88 30 79 29 64 Z" fill="#202323" />
                        <g class="cat-eye">
                            <circle cx="47" cy="55" r="11" fill="#fff" />
                            <ellipse class="cat-pupil" cx="49" cy="54" rx="2.4" ry="4" fill="#202323" />
                        </g>
                        <g class="cat-eye">
                            <circle cx="73" cy="52" r="12" fill="#fff" />
                            <ellipse class="cat-pupil" cx="75" cy="51" rx="2.4" ry="4" fill="#202323" />
                        </g>
                        <path d="M58 68 L62.5 68 L60.2 71.5 Z" fill="#fff" />
                        <path d="M60.2 71.5 L60.2 76" stroke="#fff" stroke-width="1.4" stroke-linecap="round" />
                        <path d="M30 63 C23 61 15 61 8 62 M30 67 C21 67 13 69 6 72 M31 71 C25 72 18 75 12 79 M90 61 C97 59 105 59 112 60 M90 65 C99 65 107 67 114 70 M89 69 C95 70 102 73 108 77"
                            stroke="#202323" stroke-width="1.3" stroke-linecap="round" fill="none" />
                    </g>
                </g>

                {{-- Позы прыжка — вид сбоку, морда вправо (при возвращении скрипт отражает кота).
                     Тот же язык, что у сидящего: та же голова (уши с прорезями, большие глаза, усы), пушистый хвост
                     с загнутым кончиком, залитые лапы с подушечками и просветами между ними.
                     cat-leap — оттолкнулся: вытянут, передние лапы вперёд, задние назад, хвост дугой;
                     cat-reach — снижается: лапы тянутся вниз к земле, взгляд вниз --}}
                <g class="cat-leap">
                    <path d="M10 100 C-8 98 -22 86 -24 70 C-25 58 -18 50 -10 52 C-3 54 -3 62 -9 67 C-15 73 -12 84 -2 89 C3 92 8 93 12 93 Z" fill="#202323" />
                    <path d="M16 112 C6 120 -6 130 -14 136 C-21 141 -16 147 -9 144 C3 139 15 129 27 120 Z" fill="#202323" />
                    <path d="M12 104 C2 110 -12 116 -22 118 C-31 120 -31 128 -22 128 C-8 128 8 122 22 116 Z" fill="#202323" />
                    <path d="M89 114 C99 122 111 130 121 134 C128 137 126 145 118 142 C107 138 95 130 85 122 Z" fill="#202323" />
                    <path d="M8 98 C14 82 44 76 76 80 C96 82 108 88 112 98 C114 110 104 118 86 120 C62 124 30 124 16 116 C10 112 6 106 8 98 Z" fill="#202323" />
                    <path d="M96 108 C108 112 122 116 134 116 C142 116 142 124 134 125 C120 126 104 122 92 118 Z" fill="#202323" />
                    <path d="M22 113 C12 119 2 124 -10 126 M92 117 C104 121 116 123 128 122" stroke="#d4e5e4" stroke-width="1.6" stroke-linecap="round" fill="none" />
                    <g transform="translate(70 18) rotate(8 60 58) scale(.92)">
                        <path d="M31 52 L35 12 L54 34 Z" fill="#202323" />
                        <path d="M37 21 L39 35 L45 31 Z" fill="#fff" />
                        <path d="M89 50 L87 8 L66 33 Z" fill="#202323" />
                        <path d="M84 18 L82 32 L77 29 Z" fill="#fff" />
                        <path d="M29 64 C27 42 38 29 60 29 C82 29 93 42 91 64 C90 79 80 88 60 88 C40 88 30 79 29 64 Z" fill="#202323" />
                        <circle cx="51" cy="55" r="10.5" fill="#fff" />
                        <circle cx="76" cy="52" r="11.5" fill="#fff" />
                        <ellipse cx="56" cy="54" rx="2.4" ry="4" fill="#202323" />
                        <ellipse cx="82" cy="51" rx="2.4" ry="4" fill="#202323" />
                        <path d="M64 68 L68.5 68 L66.2 71.5 Z" fill="#fff" />
                        <path d="M66.2 71.5 L66.2 76" stroke="#fff" stroke-width="1.4" stroke-linecap="round" />
                        <path d="M30 63 C23 61 15 61 8 62 M30 67 C21 67 13 69 6 72 M90 61 C97 59 105 59 112 60 M90 65 C99 65 107 67 114 70 M89 69 C95 70 102 73 108 77"
                            stroke="#202323" stroke-width="1.3" stroke-linecap="round" fill="none" />
                    </g>
                </g>
                <g class="cat-reach">
                    <path d="M10 98 C-6 92 -16 78 -14 62 C-12 51 -4 46 3 50 C9 53 7 60 2 64 C-4 70 -2 81 6 86 C10 89 13 90 16 90 Z" fill="#202323" />
                    <path d="M24 114 C20 128 18 142 19 153 C20 161 12 162 11 155 C9 143 11 127 15 112 Z" fill="#202323" />
                    <path d="M14 108 C6 120 2 134 2 146 C2 154 -6 155 -7 147 C-8 134 -2 118 6 104 Z" fill="#202323" />
                    <path d="M86 116 C90 130 92 144 92 155 C92 162 84 163 83 156 C82 144 80 130 77 120 Z" fill="#202323" />
                    <path d="M8 98 C14 82 44 76 76 80 C96 82 108 88 112 98 C114 110 104 118 86 120 C62 124 30 124 16 116 C10 112 6 106 8 98 Z" fill="#202323" />
                    <path d="M95 112 C102 124 107 138 109 150 C111 158 103 160 100 152 C97 140 92 128 86 118 Z" fill="#202323" />
                    <path d="M20 116 C17 128 16 140 17 150 M88 120 C91 132 92 142 91 152" stroke="#d4e5e4" stroke-width="1.6" stroke-linecap="round" fill="none" />
                    <g transform="translate(68 26) rotate(16 60 58) scale(.92)">
                        <path d="M31 52 L35 12 L54 34 Z" fill="#202323" />
                        <path d="M37 21 L39 35 L45 31 Z" fill="#fff" />
                        <path d="M89 50 L87 8 L66 33 Z" fill="#202323" />
                        <path d="M84 18 L82 32 L77 29 Z" fill="#fff" />
                        <path d="M29 64 C27 42 38 29 60 29 C82 29 93 42 91 64 C90 79 80 88 60 88 C40 88 30 79 29 64 Z" fill="#202323" />
                        <circle cx="51" cy="55" r="10.5" fill="#fff" />
                        <circle cx="76" cy="52" r="11.5" fill="#fff" />
                        <ellipse cx="55" cy="59" rx="2.4" ry="4" fill="#202323" />
                        <ellipse cx="80" cy="56" rx="2.4" ry="4" fill="#202323" />
                        <path d="M64 68 L68.5 68 L66.2 71.5 Z" fill="#fff" />
                        <path d="M66.2 71.5 L66.2 76" stroke="#fff" stroke-width="1.4" stroke-linecap="round" />
                        <path d="M30 63 C23 61 15 61 8 62 M30 67 C21 67 13 69 6 72 M90 61 C97 59 105 59 112 60 M90 65 C99 65 107 67 114 70 M89 69 C95 70 102 73 108 77"
                            stroke="#202323" stroke-width="1.3" stroke-linecap="round" fill="none" />
                    </g>
                </g>
            </svg>
        </div>

        {{-- Под закрытым ноутбуком — приглашение посмотреть; исчезает, как только начали прокручивать --}}
        <p class="lp__intro" x-ref="intro">
            <span>Лучше один раз увидеть</span>
        </p>

        <p class="lp__hint" x-ref="hint">
            Это настоящий кабинет учителя, только данные выдуманные — нажимайте.
            <a href="{{ url('/demo/teacher') }}" target="_blank" rel="noopener">Открыть на весь экран</a>
        </p>
    </div>
</section>

{{-- GSAP — анимация кота; оба скрипта defer: не задерживают страницу, кот появляется, когда загрузятся --}}
<script defer src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js"></script>
<script defer src="/js/about-cat.js?v={{ filemtime(public_path('js/about-cat.js')) }}"></script>

<script>
    document.addEventListener('alpine:init', function () {
        Alpine.data('aboutLaptop', function () {
            return {
                ready: false,   // пока крышка не раскрыта, клики по экрану не нужны

                init() {
                    var self = this;
                    var root = this.$el;
                    var refs = this.$refs;
                    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                    var clamp = function (v) { return Math.min(1, Math.max(0, v)); };
                    var ease = function (t) { return t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; };
                    var lerp = function (a, b, t) { return a + (b - a) * t; };

                    // Кабинет в iframe — 1440×900, подгоняем масштаб под фактический экран.
                    // Именно zoom, а не transform: scale — внутри 3D-крышки Chrome не попадает
                    // кликами в уменьшенные через transform элементы.
                    // Safari и все браузеры на iOS (движок WebKit) применяют zoom к iframe иначе: сужают окно
                    // внутри, и кабинет переключается на мобильную раскладку. Там — transform: scale
                    // (iframe остаётся 1440 пикселей, раскладка компьютерная); попадание кликов в WebKit при этом верное.
                    var webkit = /Apple/.test(navigator.vendor);
                    var fit = function () {
                        var k = refs.screen.clientWidth / 1440;
                        if (webkit) {
                            refs.demo.style.transformOrigin = '0 0';
                            refs.demo.style.transform = 'scale(' + k + ')';
                        } else {
                            refs.demo.style.zoom = k;
                        }
                    };

                    // Доводчик: прокрутка лишь «толкает» крышку. Начали раскрывать — дальше она сама
                    // плавно доходит до конца (как петля с доводчиком), вернулись к самому верху — сама закрывается.
                    // Показанное значение догоняет цель по экспоненте: быстро в начале, мягко в конце.
                    var shown = 0;      // 0 — закрыт, 1 — открыт (то, что сейчас на экране)
                    var latched = false;
                    var running = false;
                    var last = 0;

                    // Скорость прокрутки (px/мс): от неё зависит, удивится ли кот или спрыгнет сразу
                    var scrollSpeed = 0;
                    var lastScrollY = window.scrollY;
                    var lastScrollT = performance.now();
                    window.addEventListener('scroll', function () {
                        var now = performance.now();
                        var dy = Math.abs(window.scrollY - lastScrollY);
                        // Один большой рывок (колесо, тачпад) — тоже «быстро»
                        scrollSpeed = Math.max(dy / Math.max(16, now - lastScrollT), dy > 220 ? 2 : 0);
                        lastScrollY = window.scrollY;
                        lastScrollT = now;
                    }, { passive: true });

                    var target = function () {
                        if (reduced) return 1;
                        var vh = refs.sticky.clientHeight;
                        var top = root.getBoundingClientRect().top;
                        // Толкнули — прокрутили страницу хоть немного (или секция уже высоко) — открываем до конца;
                        // вернулись к самому верху страницы — закрываем
                        if (window.scrollY > 60 || top < vh * .3) latched = true;
                        else if (window.scrollY < 20) latched = false;

                        // Пока на крышке сидит кот — сначала он спрыгивает (about-cat.js), крышка раскрывается
                        // в момент толчка. Быстро прокручивают — кот прыгает сразу, без удивления.
                        var cat = window.laptopCat;
                        var catState = cat && cat.state();
                        if (latched && cat && (catState === 'sitting' || catState === 'returning' || catState === 'leaving')) {
                            if (catState !== 'leaving') cat.leave(queue, scrollSpeed > 1.2);
                            else cat.hurry(scrollSpeed > 1.2);
                            return 0;
                        }
                        return latched ? 1 : 0;
                    };

                    var faces = Array.prototype.slice.call(root.querySelectorAll('.lp__face, .lp__lid-edge'));
                    var front = root.querySelector('.lp__edge');
                    var backFace = root.querySelector('.lp__face--back');
                    var frontFace = root.querySelector('.lp__face--front');
                    var lidEdge = root.querySelector('.lp__lid-edge');
                    var bump = 0;   // градусы: крышка вздрагивает, когда кот на неё запрыгивает

                    var draw = function (open) {
                        var vh = refs.sticky.clientHeight;
                        var w = refs.lid.offsetWidth;
                        var lidH = refs.lid.offsetHeight;
                        var e = ease(open);

                        // Камера опускается с «вида сверху» до прямого взгляда на экран;
                        // в конце наклон камеры и крышки взаимно гасятся — экран смотрит прямо на зрителя
                        var tilt = lerp(-30, -9, e);
                        var lid = lerp(-90, 9, e) + bump * (1 - e);
                        var scale = lerp(.78, 1, e);

                        // Верхний край ноутбука — на одном расстоянии от текста до и после раскрытия:
                        // у закрытого верх — сам шарнир, у открытого — верх крышки (шарнир минус её высота)
                        var gap = vh * .08;
                        var hinge = lerp(gap, gap + lidH, e);

                        // Точка взгляда — относительно шарнира и в долях размера ноутбука,
                        // иначе на узком экране закрытый ноутбук виден снизу, а не сверху
                        refs.sticky.style.perspectiveOrigin = '50% ' + (hinge + lidH * lerp(.9, -.1, e)) + 'px';

                        var place = function () {
                            refs.scene.style.transform = 'translate3d(-50%, ' + (hinge - lidH) + 'px, 0) scale(' + scale + ') rotateX(' + tilt + 'deg)';
                        };
                        refs.lid.style.transform = 'rotateX(' + lid + 'deg)';
                        // Какая сторона крышки к зрителю — по углу (наклон камеры + крышки), а не backface-visibility:
                        // WebKit (Safari) её здесь не соблюдает, и поверх экрана видна задняя сторона с логотипом
                        var facing = tilt + lid > -90;
                        frontFace.style.visibility = facing ? 'visible' : 'hidden';
                        backFace.style.visibility = facing ? 'hidden' : 'visible';
                        // Кромка крышки нужна, пока ноутбук закрыт или раскрывается; у открытого она видна ребром
                        // и торчит тонкими полосками за скруглёнными углами рамки — гасим
                        lidEdge.style.opacity = clamp((.9 - e) * 4);
                        place();

                        // На середине раскрытия крышка в перспективе выше, чем в начале и в конце:
                        // меряем её настоящий верх и не пускаем ближе к тексту, чем на gap
                        var stickyTop = refs.sticky.getBoundingClientRect().top;
                        var lidTop = Math.min.apply(null, faces.map(function (f) { return f.getBoundingClientRect().top; })) - stickyTop;
                        if (lidTop < gap) {
                            hinge += gap - lidTop;
                            place();
                        }
                        refs.hint.style.top = (hinge + w * .17 + 16) + 'px';

                        // Кот сидит на закрытой крышке у правого края (прыгать недалеко), ближе к переднему краю.
                        // На телефоне крышка маленькая — кот относительно неё крупнее, иначе его не разглядеть
                        if (window.laptopCat) {
                            var lidBox = backFace.getBoundingClientRect();
                            window.laptopCat.sync(open, lidBox.left + lidBox.width * .84, lidBox.top + lidBox.height * .68 - stickyTop, lidBox.width * (innerWidth < 768 ? .25 : .145));
                        }
                        refs.hint.style.opacity = clamp((open - .9) * 10);

                        // Приглашение прокрутить — под передним краем закрытого ноутбука; начали прокручивать — гаснет
                        var frontBox = front.getBoundingClientRect();
                        refs.intro.style.top = (frontBox.bottom - stickyTop + 56) + 'px';
                        refs.intro.classList.toggle('is-hidden', latched || open > .02);
                        // Блик хромированного логотипа скользит, пока крышка поворачивается
                        refs.logo.style.setProperty('--sheen', (open * 100).toFixed(1) + '%');

                        var ready = open > .97;
                        if (ready !== self.ready) self.ready = ready;
                    };

                    var tick = function (now) {
                        var dt = Math.min(64, now - (last || now));
                        last = now;
                        var goal = target();
                        // Постоянная времени ~520 мс: полный ход крышки — около полутора секунд, с мягкой «посадкой»
                        shown += (goal - shown) * (1 - Math.exp(-dt / 520));
                        if (Math.abs(goal - shown) < .001) shown = goal;
                        draw(shown);

                        // Крышка закрылась — кот сразу запрыгивает обратно; крышка вздрагивает от приземления.
                        // «Закрылась» — по видимому углу: при shown < .1 крышка не дошла меньше чем на полградуса
                        // (ход сглажен ease), а сам shown добирается до нуля ещё секунды
                        if (goal === 0 && shown < .1 && window.laptopCat && window.laptopCat.state() === 'gone') {
                            window.laptopCat.comeBack(function () { hit(0); });
                        }

                        if (shown !== goal) {
                            requestAnimationFrame(tick);
                        } else {
                            running = false;
                        }
                    };

                    var queue = function () {
                        if (!running) {
                            running = true;
                            last = 0;
                            requestAnimationFrame(tick);
                        }
                    };

                    var render = function () { draw(shown); };

                    // Приземление кота: крышка коротко приподнимается и падает обратно
                    var hit = function (delay) {
                        setTimeout(function () {
                            var t0 = performance.now();
                            var step = function (now) {
                                var k = Math.min(1, (now - t0) / 220);
                                bump = 3.5 * Math.sin(Math.PI * k) * (1 - k * .4);
                                if (!running) render();
                                if (k < 1) requestAnimationFrame(step);
                            };
                            requestAnimationFrame(step);
                        }, delay);
                    };

                    if (window.ResizeObserver) {
                        new ResizeObserver(function () { fit(); render(); }).observe(refs.screen);
                    }

                    fit();
                    target();
                    shown = latched || reduced ? 1 : 0;
                    render();
                    window.addEventListener('scroll', queue, { passive: true });
                    window.addEventListener('resize', render);
                    // Скрипт кота грузится позже (defer) — как появится, рисуем его на месте
                    window.addEventListener('laptop-cat-ready', render);
                },
            };
        });
    });
</script>
