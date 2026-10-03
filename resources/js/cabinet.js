// Кабинеты учителя и ученика. Alpine и Livewire подключает сам Livewire.
import './bootstrap';
import './push-notifications';

/**
 * Шаринг-карточка отзыва (картинка для сторис): на телефоне — системное окно «Поделиться» (Web Share API),
 * на компьютере и там, где шаринг файлов не поддерживается, — обычное скачивание.
 *
 * Картинка генерируется на сервере несколько секунд, а браузер разрешает «Поделиться» и скачивание только
 * сразу после нажатия: если ждать загрузку внутри клика, Safari молча отменяет и то и другое. Поэтому
 * картинку загружаем заранее (serdalPrefetchReviewCard — при открытии окна или касании кнопки) и держим
 * в памяти, а по нажатию отдаём её без ожидания. Если картинка к нажатию ещё не готова — дожидаемся её
 * и, когда браузер уже не даёт поделиться, просим нажать ещё раз (картинка к этому моменту в памяти).
 * Возвращает 'shared' | 'cancelled' | 'download' | 'retry'.
 */
const reviewCards = new Map(); // url -> { blob, promise }

window.serdalPrefetchReviewCard = function (url) {
    if (!reviewCards.has(url)) {
        const entry = { blob: null };
        entry.promise = fetch(url, { credentials: 'same-origin' })
            .then((response) => {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.blob();
            })
            .then((blob) => (entry.blob = blob))
            .catch(() => {
                reviewCards.delete(url); // следующая попытка загрузит заново
                return null;
            });
        reviewCards.set(url, entry);
    }
    return reviewCards.get(url).promise;
};

const isMobileDevice = () =>
    // iPad на iPadOS представляется как Macintosh — отличаем его по мультитачу
    /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
    || (/Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints > 1);

function downloadReviewCard(url, blob) {
    // Ссылка с download, без перехода страницы — переход (location.href) браузер отменял,
    // когда следом закрывалось окно и Livewire обновлял страницу
    const link = document.createElement('a');
    link.href = blob ? URL.createObjectURL(blob) : url;
    link.download = 'serdal-review.jpg';
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
    if (blob) setTimeout(() => URL.revokeObjectURL(link.href), 60000);
}

// Вызывается синхронно из клика, пока браузер считает действие пользовательским
function deliverReviewCard(url, blob, late) {
    if (blob && isMobileDevice() && navigator.canShare) {
        const file = new File([blob], 'serdal-review.jpg', { type: 'image/jpeg' });
        if (navigator.canShare({ files: [file] })) {
            return navigator.share({ files: [file] }).then(
                () => 'shared',
                (error) => {
                    if (error.name === 'AbortError') return 'cancelled'; // пользователь закрыл окно «Поделиться»
                    if (late && error.name === 'NotAllowedError') return 'retry'; // нажатие «устарело», пока ждали картинку
                    downloadReviewCard(url, blob);
                    return 'download';
                },
            );
        }
    }
    downloadReviewCard(url, blob);
    return Promise.resolve('download');
}

window.serdalShareReviewCard = function (url) {
    const ready = reviewCards.get(url)?.blob;
    if (ready) return deliverReviewCard(url, ready, false);

    return window.serdalPrefetchReviewCard(url).then((blob) => {
        // На телефоне после ожидания браузер уже не покажет «Поделиться» — картинка теперь в памяти, нажатие ещё раз сработает
        if (blob && isMobileDevice() && navigator.canShare) {
            return deliverReviewCard(url, blob, true).then((result) => {
                if (result === 'retry') {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Картинка готова — нажмите «Поделиться» ещё раз' } }));
                }
                return result;
            });
        }
        return deliverReviewCard(url, blob, true);
    });
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
