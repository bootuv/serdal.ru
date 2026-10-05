/*
 * Кот на закрытом ноутбуке (partials/about-laptop) — SVG-персонаж, анимация на GSAP.
 *
 * Сидит на крышке: виляет хвостом, дышит, моргает, дёргает ухом, вертит головой и следит глазами за курсором.
 * Скрипт ноутбука управляет им через window.laptopCat:
 *   sync(open, x, y, width) — каждый кадр: x/y — точка на крышке под лапами (в координатах .lp__sticky);
 *   leave(done, fast)       — посетитель начал прокручивать: удивляется и спрыгивает (fast — быстрая прокрутка:
 *                             сразу прыгает); done — в момент толчка, можно раскрывать крышку;
 *   hurry(fast)             — продолжают прокручивать, пока кот удивляется: поторапливается (fast — прыгает сразу);
 *   comeBack(landed)        — крышка снова закрыта: запрыгивает обратно, landed — момент приземления;
 *   state()                 — 'sitting' | 'leaving' | 'jumping' | 'gone' | 'returning'.
 * С «уменьшить движение» кота нет (CSS), ноутбук открывается сразу.
 */
(function () {
    'use strict';

    var el = document.querySelector('.lp__cat');
    if (!el || !window.gsap || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var gsap = window.gsap;
    var q = function (s) { return el.querySelector(s); };
    var svg = q('svg');
    var cat = q('.cat');
    var body = q('.cat-body');
    var head = q('.cat-head');
    var tail = q('.cat-tail');
    var earL = q('.cat-ear--l');
    var earR = q('.cat-ear--r');
    var shadow = q('.cat-shadow');
    var eyes = el.querySelectorAll('.cat-eye');
    var pupils = el.querySelectorAll('.cat-pupil');
    var paw = q('.cat-paw');
    var leap = q('.cat-leap');
    var reach = q('.cat-reach');

    var state = 'sitting';
    var shown = false;
    var timers = {};
    var loops = [];
    var action = null;

    // Шарниры в координатах viewBox (0 0 120 170)
    gsap.set(cat, { transformOrigin: '50% 100%' });
    gsap.set(tail, { svgOrigin: '40 157' });
    gsap.set(head, { svgOrigin: '60 86' });
    gsap.set(body, { svgOrigin: '60 165' });
    gsap.set(earL, { svgOrigin: '42 44' });
    gsap.set(earR, { svgOrigin: '78 42' });
    gsap.set(paw, { svgOrigin: '67.5 118' });
    gsap.set(eyes, { transformOrigin: '50% 50%' });
    gsap.set(pupils, { transformOrigin: '50% 50%' });
    gsap.set([leap, reach], { svgOrigin: '60 108', autoAlpha: 0 });

    /* --- Жизнь на крышке ---------------------------------------------- */

    var every = function (name, min, max, fn) {
        timers[name] = gsap.delayedCall(gsap.utils.random(min, max), function () {
            fn();
            every(name, min, max, fn);
        });
    };

    var blink = function () {
        gsap.to(eyes, { scaleY: .08, duration: .07, yoyo: true, repeat: Math.random() < .25 ? 3 : 1, ease: 'power1.in' });
    };

    var twitch = function () {
        var left = Math.random() < .5;
        gsap.to(left ? earL : earR, { rotation: left ? -16 : 16, duration: .07, yoyo: true, repeat: 3, ease: 'power1.inOut' });
    };

    var tilt = function () {
        gsap.to(head, { rotation: gsap.utils.random([-9, -5, 0, 0, 4, 8]), duration: .8, ease: 'power3.inOut' });
    };

    var startIdle = function () {
        stopIdle();
        loops.push(gsap.fromTo(tail, { rotation: -8 }, { rotation: 14, duration: 1.5, ease: 'sine.inOut', yoyo: true, repeat: -1 }));
        loops.push(gsap.to(body, { scaleY: 1.025, scaleX: .99, duration: 1.8, ease: 'sine.inOut', yoyo: true, repeat: -1 }));
        every('blink', 1.8, 4.5, blink);
        every('twitch', 3, 7, twitch);
        every('tilt', 2.5, 5.5, tilt);
    };

    var stopIdle = function () {
        loops.forEach(function (t) { t.kill(); });
        loops = [];
        Object.keys(timers).forEach(function (k) { timers[k].kill(); });
        timers = {};
    };

    // Глаза следят за курсором: зрачки смещаются в его сторону, не выходя за белок
    var follow = Array.prototype.map.call(pupils, function (p) {
        return { el: p, x: gsap.quickTo(p, 'x', { duration: .35, ease: 'power3' }), y: gsap.quickTo(p, 'y', { duration: .35, ease: 'power3' }) };
    });
    window.addEventListener('pointermove', function (e) {
        if (state !== 'sitting' || !svg.getScreenCTM()) return;
        maybeCatch(e);
        var pt = new DOMPoint(e.clientX, e.clientY).matrixTransform(svg.getScreenCTM().inverse());
        follow.forEach(function (f) {
            var cx = +f.el.getAttribute('cx');
            var cy = +f.el.getAttribute('cy');
            var dx = pt.x - cx;
            var dy = pt.y - cy;
            var d = Math.sqrt(dx * dx + dy * dy) || 1;
            f.x(dx / d * Math.min(4.5, d / 20));
            f.y(dy / d * Math.min(4, d / 20));
        });
    }, { passive: true });

    /* --- Играет: ловит курсор лапой, отзывается на клик ---------------- */

    var busy = false;      // занят реакцией — не перебиваем
    var lastSwat = 0;

    /*
     * Шлёп лапой к точке (в координатах svg). Лапа не деформируется и не отрывается от тела: главное движение —
     * поворот в плече, плюс небольшой подъём и выдвижение. Подготовка (взгляд и наклон к цели) → приподнял лапу
     * → мягкий удар с наклоном к курсору и отдачей → иногда второй шлепок → неторопливый возврат.
     */
    var swat = function (pt, strong) {
        var aim = gsap.utils.clamp(-24, 24, Math.atan2(pt.x - 67, Math.max(20, pt.y - 120)) * -180 / Math.PI);
        var lean = gsap.utils.clamp(-3, 3, -aim * .12);
        var reach = strong ? 7 : 5;
        var twice = strong || Math.random() < .35;
        lastSwat = performance.now();

        var tl = gsap.timeline({ defaults: { overwrite: 'auto' } })
            // Подготовка: смотрит и чуть клонится к цели
            .to(head, { rotation: gsap.utils.clamp(-9, 9, -aim * .35), duration: .3, ease: 'sine.inOut' }, 0)
            .to(cat, { rotation: lean, svgOrigin: '60 166', duration: .34, ease: 'sine.inOut' }, 0)
            // Приподнял лапу, чуть отвёл назад
            .to(paw, { y: -9, rotation: -aim * .25, duration: .2, ease: 'sine.out' }, .08)
            // Удар: наклон к курсору, лапа чуть выдвинулась вниз-вперёд; отдача
            .to(paw, { y: reach, rotation: aim, duration: .13, ease: 'power2.in' })
            .to(paw, { y: reach - 2.5, duration: .09, ease: 'sine.out' })
            .to(paw, { y: reach - 1, duration: .1, ease: 'sine.inOut' });

        if (twice) {
            // Второй шлепок — короче
            tl.to(paw, { y: -4, rotation: aim * .7, duration: .13, ease: 'sine.out' }, '+=.04')
                .to(paw, { y: reach, rotation: aim * 1.1, duration: .11, ease: 'power2.in' })
                .to(paw, { y: reach - 1.5, duration: .1, ease: 'sine.out' });
        }

        return tl
            // Возврат: положение ведёт, поворот отстаёт, голова и корпус догоняют
            .to(paw, { y: 0, duration: .42, ease: 'sine.inOut' }, '+=.1')
            .to(paw, { rotation: 0, duration: .5, ease: 'sine.inOut' }, '<+.04')
            .to(cat, { rotation: 0, duration: .5, ease: 'sine.inOut' }, '<+.06')
            .to(head, { rotation: 0, duration: .55, ease: 'sine.inOut' }, '<+.04');
    };

    // Курсор у лап: под котом и чуть по бокам — пытается поймать, сериями с паузой
    var maybeCatch = function (e) {
        if (busy || action) return;
        var r = el.getBoundingClientRect();
        var inZone = e.clientX > r.left - r.width * .35 && e.clientX < r.right + r.width * .35
            && e.clientY > r.bottom - r.height * .22 && e.clientY < r.bottom + r.height * .45;
        if (!inZone || performance.now() - lastSwat < 1300) return;
        var pt = new DOMPoint(e.clientX, e.clientY).matrixTransform(svg.getScreenCTM().inverse());
        gsap.to(head, { rotation: gsap.utils.clamp(-10, 10, (pt.x - 60) * .2), duration: .3, overwrite: 'auto' });
        swat(pt, false);
    };

    // Клик: по очереди довольный и удивлённый; затыкали (4 клика за 2 с) — сердится и шлёпает по курсору
    var clicks = [];
    var mood = 0;
    var react = function (e) {
        if (state !== 'sitting' || busy || action) return;
        busy = true;
        var now = performance.now();
        clicks = clicks.filter(function (t) { return now - t < 2000; }).concat(now);
        var done = function () { busy = false; };
        var tl;

        if (clicks.length >= 4) {
            clicks = [];
            var pt = new DOMPoint(e.clientX, e.clientY).matrixTransform(svg.getScreenCTM().inverse());
            tl = gsap.timeline({ onComplete: done })
                .to(earL, { rotation: -32, duration: .14 })
                .to(earR, { rotation: 32, duration: .14 }, '<')
                .to(eyes, { scaleY: .5, duration: .14 }, '<')
                .to(tail, { rotation: 30, duration: .1, yoyo: true, repeat: 3, ease: 'sine.inOut' }, '<')
                .add(swat({ x: pt.x, y: Math.max(pt.y, 130) }, true), '<+.1')
                .to([earL, earR], { rotation: 0, duration: .4, ease: 'back.out(2)' }, '+=.3')
                .to(eyes, { scaleY: 1, duration: .25 }, '<');
        } else if (mood++ % 2 === 0) {
            // Доволен: зажмурился, потёрся головой, виляет хвостом, подпрыгнул
            tl = gsap.timeline({ onComplete: done })
                .to(eyes, { scaleY: .12, duration: .12, ease: 'power2.in' })
                .to(head, { rotation: -9, duration: .22, ease: 'sine.inOut' }, '<')
                .to(head, { rotation: 7, duration: .3, ease: 'sine.inOut' })
                .to(head, { rotation: 0, duration: .3, ease: 'sine.inOut' })
                .to(tail, { rotation: 26, duration: .14, yoyo: true, repeat: 3, ease: 'sine.inOut' }, .1)
                .to(cat, { scaleY: .9, scaleX: 1.06, duration: .1, ease: 'power2.in' }, .15)
                .to(el, { y: -10, duration: .16, ease: 'power2.out' }, .25)
                .to(cat, { scaleY: 1.04, scaleX: .97, duration: .16 }, .25)
                .to(el, { y: 0, duration: .16, ease: 'power2.in' }, .41)
                .to(cat, { scaleY: 1, scaleX: 1, duration: .4, ease: 'elastic.out(1, .5)' }, .57)
                .to(eyes, { scaleY: 1, duration: .16 }, .95);
        } else {
            // Удивился: уши торчком, глаза круглые, подпрыгнул повыше
            tl = gsap.timeline({ onComplete: done })
                .to(eyes, { scale: 1.25, duration: .14, ease: 'back.out(3)' })
                .to(pupils, { x: 0, y: 0, scale: .6, duration: .12 }, '<')
                .to(earL, { rotation: 10, duration: .14 }, '<')
                .to(earR, { rotation: -10, duration: .14 }, '<')
                .to(cat, { scaleY: .86, scaleX: 1.08, duration: .1, ease: 'power2.in' }, '<')
                .to(el, { y: -22, duration: .2, ease: 'power2.out' })
                .to(cat, { scaleY: 1.08, scaleX: .95, duration: .2 }, '<')
                .to(tail, { rotation: 22, scale: 1.1, duration: .2 }, '<')
                .to(el, { y: 0, duration: .2, ease: 'power2.in' })
                .to(cat, { scaleY: .9, scaleX: 1.06, duration: .08 })
                .to(cat, { scaleY: 1, scaleX: 1, duration: .45, ease: 'elastic.out(1, .45)' })
                .to([earL, earR], { rotation: 0, duration: .3 }, '<')
                .to(eyes, { scale: 1, duration: .3 }, '<+.3')
                .to(pupils, { scale: 1, duration: .3 }, '<')
                .to(tail, { scale: 1, duration: .3 }, '<');
        }
    };
    cat.addEventListener('click', react);

    /* --- Спрыгнуть и вернуться ---------------------------------------- */

    var reset = function () {
        gsap.set([earL, earR, head], { rotation: 0 });
        gsap.set(paw, { x: 0, y: 0, rotation: 0 });
        gsap.set(tail, { rotation: 0, scale: 1 });
        gsap.set(eyes, { scale: 1 });
        gsap.set(pupils, { x: 0, y: 0, scale: 1 });
        gsap.set(cat, { scaleX: 1, scaleY: 1, rotation: 0, autoAlpha: 1 });
        gsap.set([leap, reach], { autoAlpha: 0, rotation: 0, scaleX: 1, scaleY: 1 });
        gsap.set(el, { scaleX: 1, rotation: 0 });
    };

    /*
     * Полёт — по баллистической дуге вправо-вниз, за нижний край окна: исчезает (и появляется) уже там, где не видно.
     * По горизонтали — постоянная скорость; по вертикали — как под силой тяжести: подъём на h с замедлением
     * к верхней точке (за up секунд), потом падение с ускорением до dy (за down). Оба участка — куски одной параболы:
     * при одинаковом ускорении время участка пропорционально корню из его высоты.
     */
    var flight = function () {
        var r = el.getBoundingClientRect();
        var w = el.offsetWidth;
        var top = r.top - (gsap.getProperty(el, 'y') || 0);
        var dy = Math.max(w * 2, window.innerHeight - top + el.offsetHeight * .4);
        var h = w * .5;
        var time = Math.min(1.25, Math.max(.8, .6 + dy / w * .07));
        var up = time / (1 + Math.sqrt((dy + h) / h));
        return { w: w, dx: w * 4, dy: dy, h: h, time: time, up: up, down: time - up };
    };

    /*
     * fast — быстрая прокрутка: без удивления, сразу присел и прыгнул (и сам прыжок быстрее).
     * done вызывается в момент толчка: крышка раскрывается, пока кот летит, а не после приземления.
     */
    var leave = function (done, fast) {
        if (action) action.kill();
        gsap.killTweensOf([paw, eyes, earL, earR, head, cat, el]);
        gsap.set(cat, { rotation: 0 });
        gsap.set(paw, { x: 0, y: 0, rotation: 0 });
        busy = false;
        stopIdle();
        state = 'leaving';
        var f = flight();
        var w = f.w;
        var takeoff = function () {
            state = 'jumping';
            if (done) done();
        };

        action = gsap.timeline({
            onComplete: function () {
                state = 'gone';
                action = null;
            },
        });

        if (!fast) {
            action
                // Удивился: глаза круглые, зрачки в точку, уши назад, хвост трубой
                .to(pupils, { x: 0, y: 0, scale: .5, duration: .14, ease: 'power2.out' })
                .to(eyes, { scale: 1.2, duration: .22, ease: 'back.out(2.2)' }, '<')
                .to(earL, { rotation: -28, duration: .2, ease: 'power2.out' }, '<')
                .to(earR, { rotation: 28, duration: .2, ease: 'power2.out' }, '<')
                .to(tail, { rotation: 24, scale: 1.1, duration: .26, ease: 'back.out(1.8)' }, '<')
                .to(head, { rotation: 5, duration: .22, ease: 'sine.inOut' }, '<')
                // Посмотрел, куда прыгать
                .to(head, { rotation: 12, duration: .2, ease: 'sine.inOut' }, '+=.06')
                .to(pupils, { x: 4, y: 1, scale: .8, duration: .2, ease: 'sine.inOut' }, '<');
        }

        action
            // Присел перед прыжком, хвост опустил
            .addLabel('crouch')
            .to(cat, { scaleY: .8, scaleX: 1.1, duration: fast ? .14 : .22, ease: 'sine.inOut' }, fast ? 0 : '+=.02')
            .to(tail, { rotation: -10, scale: 1, duration: fast ? .14 : .22, ease: 'sine.inOut' }, '<')
            .addLabel('jump')
            .call(takeoff, null, 'jump')
            // Оттолкнулся: сидячая поза перетекает в позу прыжка — вытянут, лапы вперёд и назад, нос вверх
            .to(cat, { autoAlpha: 0, duration: .07 }, 'jump')
            .fromTo(leap, { autoAlpha: 0, scaleX: .85, scaleY: 1.1, rotation: -16 },
                { autoAlpha: 1, scaleX: 1.08, scaleY: .95, duration: .2, ease: 'sine.out' }, 'jump')
            // Корпус — по направлению полёта: нос вверх на взлёте, горизонтально в верхней точке, дальше всё круче вниз
            .to(leap, { rotation: 0, duration: f.up, ease: 'sine.out' }, 'jump')
            .to(el, { x: f.dx, duration: f.time, ease: 'none' }, 'jump')
            .to(el, { y: -f.h, duration: f.up, ease: 'power2.out' }, 'jump')
            .to(el, { y: f.dy, duration: f.down, ease: 'power2.in' }, 'jump+=' + f.up)
            .to(shadow, { opacity: 0, duration: .18 }, 'jump')
            // Снижается: поза прыжка перетекает в «лапы вниз», корпус клонится по траектории
            .to(leap, { autoAlpha: 0, duration: .1 }, 'jump+=' + f.up)
            .fromTo(reach, { autoAlpha: 0, rotation: 0 }, { autoAlpha: 1, duration: .1 }, 'jump+=' + f.up)
            .to(reach, { rotation: 52, duration: f.down, ease: 'power1.in' }, 'jump+=' + f.up)
            // Уже за краем окна — прячем
            .set(el, { autoAlpha: 0 }, 'jump+=' + f.time);

        // Быстро прокрутили — и сама анимация живее
        if (fast) action.timeScale(1.6);
    };

    // Пока кот ещё удивляется, посетитель продолжает прокручивать: поторапливается,
    // а если прокрутил быстро — бросает удивляться и сразу прыгает
    var hurry = function (fast) {
        if (state !== 'leaving' || !action) return;
        if (fast && action.time() < action.labels.crouch) action.seek('crouch');
        action.timeScale(Math.max(action.timeScale(), fast ? 1.8 : 1.4));
    };

    var comeBack = function (landed) {
        if (action) action.kill();
        state = 'returning';
        reset();
        var f = flight();
        var w = f.w;
        var t = f.time;
        // Выпрыгивает снизу из-за края окна справа налево в позе прыжка (отражён), перед крышкой выставляет лапы, садится
        gsap.set(el, { x: f.dx, y: f.dy, scaleX: -1, autoAlpha: 1 });
        gsap.set(shadow, { opacity: 0 });
        gsap.set(cat, { autoAlpha: 0 });
        gsap.set(leap, { autoAlpha: 1, rotation: -18, scaleX: 1.1, scaleY: .95 });

        action = gsap.timeline({
            onComplete: function () {
                state = 'sitting';
                action = null;
                startIdle();
            },
        })
            // Та же дуга задом наперёд: долгий подъём снизу с замедлением, короткое падение на крышку
            .to(el, { x: 0, duration: t, ease: 'none' }, 0)
            .to(el, { y: -f.h, duration: f.down, ease: 'power2.out' }, 0)
            .to(el, { y: 0, duration: f.up, ease: 'power2.in' }, f.down)
            .fromTo(leap, { rotation: -50 }, { rotation: 0, duration: f.down, ease: 'power1.out' }, 0)
            .to(leap, { autoAlpha: 0, duration: .1 }, f.down)
            .fromTo(reach, { autoAlpha: 0, rotation: 0 }, { autoAlpha: 1, duration: .1 }, f.down)
            .to(reach, { rotation: 12, duration: f.up, ease: 'sine.in' }, f.down)
            .to(shadow, { opacity: .16, duration: .2 }, f.down + f.up * .5)
            // Приземлился: снова сидит, присел от удара и пружинисто выпрямился, голова — ещё в сторону полёта
            .set(reach, { autoAlpha: 0 }, t)
            .set(el, { scaleX: 1 }, t)
            .set(cat, { autoAlpha: 1, scaleY: .78, scaleX: 1.12 }, t)
            .set(head, { rotation: -10 }, t)
            .call(function () { if (landed) landed(); }, null, t)
            .to(cat, { scaleY: 1, scaleX: 1, duration: .75, ease: 'elastic.out(1, .45)' }, t + .02)
            .to(head, { rotation: 0, duration: .6, ease: 'power2.out' }, t + .18)
            .fromTo(tail, { rotation: -14 }, { rotation: 10, duration: .5, ease: 'back.out(2)' }, t + .02);
    };

    /* --- Связь со скриптом ноутбука ---------------------------------- */

    window.laptopCat = {
        state: function () { return state; },
        leave: leave,
        hurry: hurry,
        comeBack: comeBack,

        sync: function (open, x, y, width) {
            // Ноутбук открыт (страницу открыли прокрученной) — кота на крышке нет. Порог заметный:
            // после возвращения кота крышка ещё дозакрывается по плавной кривой, это не «открыт»
            if (open > .001) {
                if (open > .5 && state === 'sitting' && !action) {
                    state = 'gone';
                    stopIdle();
                    gsap.set(el, { autoAlpha: 0 });
                }
                return;
            }
            el.style.width = width + 'px';
            el.style.left = (x - width * .53) + 'px';
            el.style.top = (y - width * 170 / 120) + 'px';
            if (!shown && state === 'sitting') {
                shown = true;
                gsap.fromTo(el, { autoAlpha: 0, y: -8 }, { autoAlpha: 1, y: 0, duration: .4, ease: 'power2.out' });
                startIdle();
            }
        },
    };

    // Вкладка в фоне — анимации стоят (GSAP сам), а таймеры не копятся
    document.addEventListener('visibilitychange', function () {
        if (state !== 'sitting') return;
        if (document.hidden) stopIdle();
        else startIdle();
    });

    window.dispatchEvent(new Event('laptop-cat-ready'));
})();
