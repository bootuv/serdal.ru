/**
 * Лайтбокс кабинета (resources/views/components/ui/lightbox.blade.php): клик по ссылке на картинку
 * открывает её поверх страницы вместо новой вкладки.
 *
 * Картинка — ссылка с атрибутом data-lightbox или с адресом, оканчивающимся на расширение изображения
 * (адреса S3 с подписью тоже подходят: смотрим на путь, без параметров). data-lightbox="off" — не открывать.
 * Соседние картинки (в пределах [data-lightbox-group], окна, раздела) листаются.
 * Клик с Cmd/Ctrl/Shift или средней кнопкой по-прежнему открывает файл в новой вкладке.
 */
const IMAGE = /\.(png|jpe?g|gif|webp|avif|bmp|svg)$/i;

function isImageLink(a) {
    if (a.dataset.lightbox === 'off') return false;
    if (a.dataset.lightbox !== undefined) return true;

    try {
        return IMAGE.test(new URL(a.href, location.href).pathname);
    } catch {
        return false;
    }
}

function nameOf(a) {
    const img = a.querySelector('img');
    const text = a.dataset.name || img?.getAttribute('alt') || a.getAttribute('download') || a.textContent.trim();
    if (text) return text;

    try {
        return decodeURIComponent(new URL(a.href, location.href).pathname.split('/').pop());
    } catch {
        return '';
    }
}

document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

    const link = e.target.closest('a[href]');
    if (!link || !isImageLink(link)) return;

    e.preventDefault();

    const scope = link.closest('[data-lightbox-group], [role="dialog"], section, main') || document;
    const seen = new Set();
    const items = [...scope.querySelectorAll('a[href]')]
        .filter((a) => isImageLink(a) && !seen.has(a.href) && seen.add(a.href))
        .map((a) => ({ src: a.href, name: nameOf(a) }));
    const index = Math.max(0, items.findIndex((it) => it.src === link.href));

    window.dispatchEvent(new CustomEvent('lightbox', { detail: { items, index } }));
}, true);
