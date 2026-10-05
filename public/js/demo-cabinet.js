/*
 * Демо-кабинет (App\Demo, /demo/teacher/…): вместо Livewire.
 *
 * Шаблоны — настоящие, с wire:click, wire:model.live, $wire.метод(). Здесь эти вызовы
 * перехватываются и выполняются по таблице window.DEMO.actions (см. App\Demo\Screen):
 * состояние экрана (открытое окно, вкладка, фильтр) хранится в адресе, поэтому почти любое
 * действие — это переход на тот же экран с другими параметрами, который сервер рисует заново.
 *
 * Подключается в <head> до Alpine (раскладка cabinet, prop demo).
 */
(function () {
    'use strict';

    var framed = window.top !== window;
    var cfg = function () { return window.DEMO || {}; };

    // Livewire прячет индикаторы загрузки своими стилями — без него они висели бы на экране всегда
    var hidden = ['loading', 'loading.delay', 'loading.flex', 'loading.inline-flex', 'loading.block', 'loading.inline',
        'loading.inline-block', 'loading.grid', 'loading.table', 'loading.remove', 'offline', 'dirty'];
    var style = document.createElement('style');
    style.textContent = hidden.map(function (n) { return '[wire\\:' + n.replace('.', '\\.') + ']'; }).join(',')
        + '{display:none!important}[x-cloak]{display:none!important}';
    document.head.appendChild(style);

    /* --- Навигация ---------------------------------------------------- */

    function navigate(url) {
        var target = new URL(url, location.href);
        try {
            // Тот же экран с другим состоянием — сохраняем прокрутку, чтобы окно открылось «на месте»
            if (target.pathname === location.pathname) {
                sessionStorage.setItem('demo-scroll', JSON.stringify({ path: target.pathname, y: window.scrollY }));
            }
        } catch (e) {}

        // Внутри iframe не засоряем историю страницы «О платформе»: «Назад» в браузере — с сайта, а не по кабинету
        if (framed) location.replace(target.href);
        else location.assign(target.href);
    }

    function toast(message, tone, later) {
        if (later) {
            try { sessionStorage.setItem('demo-toast', JSON.stringify({ message: message, tone: tone || 'ok' })); } catch (e) {}
            return;
        }
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: message, tone: tone || 'ok' } }));
    }

    /* --- Действия ----------------------------------------------------- */

    // {0} — аргумент вызова, {@draft} — текущее значение поля с wire:model="draft"
    function fill(value, args) {
        if (typeof value !== 'string') return value;
        return value
            .replace(/\{(\d+)\}/g, function (m, i) { return args[i] === undefined ? '' : String(args[i]); })
            .replace(/\{@([\w.]+)\}/g, function (m, name) {
                var field = Array.prototype.find.call(document.querySelectorAll('input, textarea, select'), function (el) {
                    var a = wireAttr(el, 'wire:model');
                    return a && a.value === name;
                });
                return field ? field.value : '';
            });
    }

    function listOf(params, key) {
        var v = params.get(key);
        return v ? v.split(',') : [];
    }

    function run(action, args) {
        var url = new URL(location.href);
        var params = url.searchParams;
        var changed = false;

        if (action.close) {
            (cfg().modal || []).forEach(function (k) { params.delete(k); });
            changed = true;
        }

        if (action.set) {
            Object.keys(action.set).forEach(function (k) {
                var v = fill(action.set[k], args);
                if (v === null || v === undefined) params.delete(k);
                else params.set(k, v);
            });
            changed = true;
        }

        ['toggle', 'add', 'remove'].forEach(function (op) {
            if (!action[op]) return;
            var key = action[op];
            var value = String(args[0]);
            var list = listOf(params, key);
            var has = list.indexOf(value) !== -1;
            if (op === 'add' && !has) list.push(value);
            if (op === 'remove' || (op === 'toggle' && has)) list = list.filter(function (x) { return x !== value; });
            if (op === 'toggle' && !has) list.push(value);
            params.set(key, list.join(','));
            changed = true;
        });

        var go = action.go ? new URL(fill(action.go, args), location.href) : (changed ? url : null);

        if (action.toast) toast(fill(action.toast, args), action.tone, !!go);
        if (go) navigate(go.href);
    }

    function call(name, args) {
        args = args || [];
        var c = cfg();

        // $set(имя, значение, false) — без запроса к серверу (x-ui.code-input на каждой цифре): в демо ничего не делаем
        if ((name === '$set' || name === 'set') && args[2] === false) return;
        if (name === '$dispatch') return window.dispatchEvent(new CustomEvent(args[0], { detail: args[1] || {} }));
        if (name === '$set' || name === 'set') return run({ set: (function () { var o = {}; o[args[0]] = args[1] === true ? '1' : (args[1] === false ? null : args[1]); return o; })() }, []);
        if (name === '$toggle') {
            var on = new URL(location.href).searchParams.get(args[0]) === '1';
            var o = {}; o[args[0]] = on ? null : '1';
            return run({ set: o }, []);
        }
        if (name === '$refresh' || name === '$commit' || name === '$parent') return;

        var action = (c.actions || {})[name];
        if (action) return run(action, args);
        if (/^(close|cancel|hide)/i.test(name)) return run({ close: true }, args);

        toast(c.saveText || 'Это демо — изменения не сохраняются');
    }

    // «markPaid(5)», «$set('open', 'plan')», «closePlan» → ['markPaid', [5]]
    function parse(expr) {
        var m = String(expr).trim().match(/^([\w$.]+)\s*(?:\(([\s\S]*)\))?\s*;?$/);
        if (!m) return null;
        var args = [];
        if (m[2] && m[2].trim()) {
            try { args = Function('"use strict"; return [' + m[2] + '];')(); } catch (e) { args = []; }
        }
        return [m[1], args];
    }

    function wireAttr(el, prefix) {
        if (!el || !el.attributes) return null;
        for (var i = 0; i < el.attributes.length; i++) {
            var a = el.attributes[i];
            if (a.name === prefix || a.name.indexOf(prefix + '.') === 0) return a;
        }
        return null;
    }

    function closestWire(el, prefix) {
        while (el && el !== document) {
            var a = wireAttr(el, prefix);
            if (a) return [el, a];
            el = el.parentNode;
        }
        return null;
    }

    /* --- Перехват событий -------------------------------------------- */

    document.addEventListener('click', function (e) {
        var hit = closestWire(e.target, 'wire:click');
        if (hit) {
            e.preventDefault();
            var parsed = parse(hit[1].value);
            if (parsed) call(parsed[0], parsed[1]);
            return;
        }

        var link = e.target.closest && e.target.closest('a[href]');
        if (!link || link.hasAttribute('data-demo-exit') || e.defaultPrevented) return;
        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || /^(mailto|tel|javascript):/.test(href)) return;

        var url = new URL(href, location.href);
        // Картинки открывает лайтбокс кабинета (resources/js/lightbox.js)
        if (/\.(png|jpe?g|gif|webp|avif|svg)$/i.test(url.pathname) && link.dataset.lightbox !== 'off') return;
        // Внутри демо и «в новой вкладке» (класс, запись) открываем на месте — демо не расползается по вкладкам
        if (url.origin === location.origin && url.pathname.indexOf('/demo/') === 0) {
            e.preventDefault();
            navigate(url.href);
        } else if (url.origin === location.origin) {
            // Ссылка за пределы демо (выход, файл, страница на сайте) — не уводим посетителя
            e.preventDefault();
            toast(cfg().leaveText || cfg().saveText);
        } else if (framed && link.target !== '_blank') {
            e.preventDefault();
            window.open(url.href, '_blank', 'noopener');
        }
    }, true);

    document.addEventListener('submit', function (e) {
        e.preventDefault();
        var hit = wireAttr(e.target, 'wire:submit');
        var parsed = hit && parse(hit.value);
        if (parsed) call(parsed[0], parsed[1]);
        else toast(cfg().saveText);
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        var hit = closestWire(e.target, 'wire:keydown.enter');
        if (!hit) return;
        e.preventDefault();
        var parsed = parse(hit[1].value);
        if (parsed) call(parsed[0], parsed[1]);
    }, true);

    var timers = {};
    function onModel(e) {
        var el = e.target;
        var attr = wireAttr(el, 'wire:model');
        if (attr && el.type === 'file' && e.type === 'change') return toast('Это демо — файлы не загружаются. Зарегистрируйтесь, чтобы загрузить свои');
        if (!attr || attr.name.indexOf('.live') === -1) return;
        var name = attr.value;
        var value = el.value;
        if (el.type === 'checkbox') {
            // Список галочек с общим wire:model — значения отмеченных через запятую; одиночная — 1 / ничего
            var group = Array.prototype.filter.call(document.querySelectorAll('input[type=checkbox]'), function (x) {
                var a = wireAttr(x, 'wire:model');
                return a && a.value === attr.value;
            });
            value = group.length > 1 || (el.value && el.value !== 'on')
                ? group.filter(function (x) { return x.checked; }).map(function (x) { return x.value; }).join(',')
                : (el.checked ? '1' : null);
        }
        var text = e.type === 'input' && /^(text|search|email|tel|number|url)$/.test(el.type || 'text') && el.tagName !== 'SELECT';
        if (e.type === 'change' && text) return;
        if (e.type === 'input' && !text) return;
        clearTimeout(timers[name]);
        timers[name] = setTimeout(function () {
            var o = {}; o[name] = value;
            run({ set: o }, []);
        }, text ? 600 : 0);
    }
    document.addEventListener('input', onModel, true);
    document.addEventListener('change', onModel, true);

    // Колокольчик: в кабинете панель открывает Livewire (show()), здесь — параметр адреса (App\Demo\Notifications)
    window.addEventListener('notifications-open', function () { run({ set: { notifications: '1' } }, []); });

    /* --- $wire для Alpine -------------------------------------------- */

    document.addEventListener('alpine:init', function () {
        window.Alpine.magic('wire', function () {
            return new Proxy({}, {
                get: function (t, prop) {
                    if (typeof prop !== 'string') return undefined;
                    var props = cfg().props || {};
                    if (Object.prototype.hasOwnProperty.call(props, prop)) return props[prop];
                    if (prop === 'entangle' || prop === 'get' || prop === '$get') return function (k) { return props[k]; };
                    if (prop === 'call' || prop === '$call') return function (name) { call(name, Array.prototype.slice.call(arguments, 1)); return Promise.resolve(); };
                    if (prop === '$wire' || prop === '__instance') return undefined;
                    // Как у Livewire: вызов метода возвращает Promise (шаблоны пишут $wire.openUpload().then(…))
                    return function () { call(prop, Array.prototype.slice.call(arguments)); return Promise.resolve(); };
                },
                set: function () { return true; },
            });
        });
    });

    /* --- После загрузки ---------------------------------------------- */

    // Значения полей wire:model из данных экрана (planDate, planDuration, planSlots.0.time …)
    function fillModels() {
        var models = cfg().models || {};
        document.querySelectorAll('input, select, textarea').forEach(function (el) {
            var attr = wireAttr(el, 'wire:model');
            if (!attr) return;
            var value = attr.value.split('.').reduce(function (o, k) { return o == null ? undefined : o[k]; }, models);
            if (value === undefined || value === null) return;
            if (el.type === 'checkbox') {
                el.checked = Array.isArray(value) ? value.map(String).indexOf(el.value) !== -1 : !!value;
            } else if (el.type === 'radio') {
                el.checked = String(value) === el.value;
            } else if (el.type !== 'file') {
                el.value = Array.isArray(value) ? value.join(', ') : value;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        fillModels();
        try {
            var s = JSON.parse(sessionStorage.getItem('demo-scroll') || 'null');
            sessionStorage.removeItem('demo-scroll');
            if (s && s.path === location.pathname) window.scrollTo(0, s.y);

            var t = JSON.parse(sessionStorage.getItem('demo-toast') || 'null');
            sessionStorage.removeItem('demo-toast');
            if (!t && cfg().notice) t = { message: cfg().notice, tone: 'ok' };
            // Тост слушает Alpine-компонент — ждём, пока он поднимется
            if (t) setTimeout(function () { toast(t.message, t.tone); }, 300);
        } catch (e) {}

        // На весь экран (не в ноутбуке) — полоска о том, что это демо, и кнопка «Выйти»
        if (!framed) {
            var bar = document.createElement('div');
            bar.setAttribute('role', 'note');
            bar.style.cssText = 'position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:50;display:flex;align-items:center;gap:12px;'
                + 'padding:8px 8px 8px 16px;border-radius:16px;background:#202323;color:#fff;font:500 14px/20px Inter,sans-serif;box-shadow:0 8px 24px rgba(20,32,40,.2);white-space:nowrap';
            // Не по центру внизу — там тосты. Компьютер: слева внизу; телефон: под верхней панелью кабинета
            var narrow = window.innerWidth < 1024;
            bar.style.transform = 'none';
            if (narrow) { bar.style.bottom = 'auto'; bar.style.top = '72px'; bar.style.left = '50%'; bar.style.transform = 'translateX(-50%)'; }
            else { bar.style.left = 'auto'; bar.style.right = '16px'; }
            bar.innerHTML = '<span>' + (narrow ? 'Демо · данные выдуманные' : 'Демо кабинета учителя · данные выдуманные') + '</span>'
                + '<a data-demo-exit href="' + (cfg().exitUrl || '/') + '" style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:12px;background:#fff;color:#202323;text-decoration:none;font-weight:600">'
                + '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>Выйти</a>';
            // Демо открыли в новой вкладке со страницы «О платформе» — закрываем её; если браузер не даёт
            // (вкладку открыли иначе или в ней уже переходили), просто уходим на «О платформе»
            bar.querySelector('a').addEventListener('click', function (e) {
                e.preventDefault();
                var href = this.href;
                window.close();
                setTimeout(function () { if (!window.closed) location.href = href; }, 150);
            });
            document.body.appendChild(bar);
        }
    });

    /* --- Предзагрузка экранов ---------------------------------------- */

    // Экран рисует сервер, и каждый клик — это новая страница. Чтобы не ждать сеть, экраны скачиваются
    // заранее в Cache Storage, а переход отдаёт из него service worker (public/demo-sw.js; HTTP-кэш
    // не годится — во фрейме ноутбука Chrome не берёт из него страницу, скачанную fetch()).
    // Ссылка под курсором — сразу, пункты меню — когда посетитель впервые взаимодействует с демо
    // (не раньше: ноутбук грузится вместе с «О платформе», а многие до него не долистают).
    // Не больше двух запросов разом: пачка запросов забивает сервер, и клик ждал бы в очереди.
    var PAGES = 'demo-pages';      // имя кэша и срок свежести — те же, что в demo-sw.js
    var FRESH_MS = 120000;
    var canCache = !!(window.caches && navigator.serviceWorker);

    if (canCache) {
        navigator.serviceWorker.register('/demo-sw.js', { scope: '/demo/' }).catch(function () {});
    }

    function fresh(response) {
        var date = response && Date.parse(response.headers.get('Date') || '');
        return !!date && Date.now() - date < FRESH_MS;
    }

    var queued = {}, queue = [], active = 0;
    function prefetch(href, urgent) {
        if (!canCache) return;
        var url;
        try { url = new URL(href, location.href); } catch (e) { return; }
        url.hash = '';
        if (url.origin !== location.origin || url.pathname.indexOf('/demo/') !== 0) return;
        if (url.href === location.href.split('#')[0]) return;
        if (queued[url.href]) {
            // Уже ждёт в очереди, а посетитель навёл на неё курсор — вперёд
            var i = queue.indexOf(url.href);
            if (urgent && i > 0) { queue.splice(i, 1); queue.unshift(url.href); }
            return;
        }
        queued[url.href] = true;
        urgent ? queue.unshift(url.href) : queue.push(url.href);
        pump();
    }

    function pump() {
        while (active < 2 && queue.length) {
            active++;
            load(queue.shift()).catch(function () {}).then(function () { active--; pump(); });
        }
    }

    function load(href) {
        return caches.open(PAGES).then(function (cache) {
            return cache.match(href).then(function (hit) {
                if (fresh(hit)) return;
                return fetch(href, { credentials: 'same-origin' }).then(function (response) {
                    if (response.ok) return cache.put(href, response);
                });
            });
        });
    }

    function onHover(e) {
        var link = e.target.closest && e.target.closest('a[href]');
        if (link && !link.hasAttribute('data-demo-exit')) prefetch(link.getAttribute('href'), true);
    }
    document.addEventListener('pointerover', onHover, { passive: true });
    document.addEventListener('focusin', onHover);

    var menuDone = false;
    function prefetchMenu() {
        if (menuDone) return;
        menuDone = true;
        var later = window.requestIdleCallback || function (fn) { return setTimeout(fn, 300); };
        later(function () {
            // Экраны без параметров (пункты меню и разделы), не больше двадцати
            var seen = {};
            Array.prototype.forEach.call(document.querySelectorAll('a[href]'), function (a) {
                var url = new URL(a.getAttribute('href'), location.href);
                if (url.search || url.pathname.indexOf('/demo/') !== 0 || Object.keys(seen).length >= 20) return;
                seen[url.pathname] = true;
            });
            Object.keys(seen).forEach(function (path) { prefetch(path); });
        });
    }
    ['pointermove', 'touchstart', 'keydown'].forEach(function (type) {
        document.addEventListener(type, prefetchMenu, { once: true, passive: true });
    });

    // Картинка отзыва для сторис (в кабинете — cabinet.js): в демо картинка уже в адресе (data:), «поделиться» — тост
    window.serdalPrefetchReviewCard = function () { return Promise.resolve(null); };
    window.serdalShareReviewCard = function () { toast(cfg().saveText); return Promise.resolve('cancelled'); };

    window.demoCabinet = { call: call, run: run, toast: toast };
})();
