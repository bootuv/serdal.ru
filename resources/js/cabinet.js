// Кабинеты учителя и ученика. Alpine и Livewire подключает сам Livewire.
import './bootstrap';
import './push-notifications';

/**
 * Шаринг-карточка отзыва (картинка для сторис): на телефоне — системное окно «Поделиться» (Web Share API),
 * на компьютере и там, где шаринг файлов не поддерживается, — обычное скачивание.
 * Возвращает 'shared' | 'cancelled' | 'download'.
 */
window.serdalShareReviewCard = async function (url) {
    // iPad на iPadOS представляется как Macintosh — отличаем его по мультитачу
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
        || (/Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints > 1);

    let blob = null;
    try {
        const response = await fetch(url, { credentials: 'same-origin' });
        if (!response.ok) throw new Error('HTTP ' + response.status);
        blob = await response.blob();
    } catch (error) {
        blob = null;
    }

    if (blob && isMobile && navigator.canShare) {
        const file = new File([blob], 'serdal-review.jpg', { type: 'image/jpeg' });
        try {
            if (navigator.canShare({ files: [file] })) {
                await navigator.share({ files: [file] });
                return 'shared';
            }
        } catch (error) {
            if (error.name === 'AbortError') return 'cancelled'; // пользователь закрыл окно «Поделиться»
        }
    }

    // Компьютер и запасной вариант: сохраняем файл через ссылку с download, без перехода страницы —
    // переход (location.href) браузер отменял, когда следом закрывалось окно и Livewire обновлял страницу
    const link = document.createElement('a');
    link.href = blob ? URL.createObjectURL(blob) : url;
    link.download = 'serdal-review.jpg';
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
    if (blob) setTimeout(() => URL.revokeObjectURL(link.href), 60000);
    return 'download';
};
import './rich-editor';
import './block-editor';
import './lightbox';
import './video-player';
import './photo-crop';
import './tour';

/**
 * Сбои запросов Livewire: вместо стандартного окна Livewire (английский confirm для 419, HTML ошибки для 500)
 * показываем своё окно из раскладки кабинета (событие cabinet-request-failed).
 */
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status === 419 || status >= 500) {
                preventDefault();
                window.dispatchEvent(new CustomEvent('cabinet-request-failed', { detail: { kind: status === 419 ? 'expired' : 'error' } }));
            }
        });
    });
});
