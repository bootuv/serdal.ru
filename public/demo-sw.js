/*
 * Service worker демо-кабинета (scope /demo/, регистрирует public/js/demo-cabinet.js).
 *
 * Зачем: каждый клик в демо — новая страница с сервера. demo-cabinet.js скачивает экраны заранее
 * в Cache Storage, а этот воркер отдаёт переход из него. Обычный HTTP-кэш здесь не помогает:
 * Chrome не отдаёт страницу во фрейме (ноутбук на «О платформе») из того, что скачал fetch().
 *
 * Экран свежий, пока ему меньше FRESH_MS (по заголовку Date ответа) — время в демо «живое».
 * Имя кэша и срок — те же, что в demo-cabinet.js.
 */
var CACHE = 'demo-pages';
var FRESH_MS = 120000;

function fresh(response) {
    var date = response && Date.parse(response.headers.get('Date') || '');
    return !!date && Date.now() - date < FRESH_MS;
}

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (e) {
    // Сразу берём под себя уже открытый ноутбук — иначе воркер заработал бы только со следующего захода
    e.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function (e) {
    var req = e.request;
    if (req.method !== 'GET' || req.mode !== 'navigate') return;
    var url = new URL(req.url);
    if (url.origin !== self.location.origin || url.pathname.indexOf('/demo/') !== 0) return;
    url.hash = '';

    e.respondWith(caches.open(CACHE).then(function (cache) {
        return cache.match(url.href).then(function (hit) {
            if (fresh(hit)) return hit;

            return fetch(req).then(function (response) {
                if (response.ok) cache.put(url.href, response.clone());
                return response;
            }, function (error) {
                // Нет сети — лучше устаревший экран, чем ошибка
                if (hit) return hit;
                throw error;
            });
        });
    }));
});
