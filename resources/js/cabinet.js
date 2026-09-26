// Кабинеты учителя и ученика. Alpine и Livewire подключает сам Livewire.
import './bootstrap';
import './push-notifications';

/**
 * Шаринг-карточка отзыва (картинка для сторис): на телефоне — системное окно «Поделиться» (Web Share API),
 * на компьютере и там, где шаринг файлов не поддерживается, — обычное скачивание.
 * Та же функция есть в старом кабинете (render hook в AppServiceProvider).
 * Возвращает 'shared' | 'cancelled' | 'download'.
 */
window.serdalShareReviewCard = async function (url) {
    // iPad на iPadOS представляется как Macintosh — отличаем его по мультитачу
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
        || (/Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints > 1);

    if (isMobile && navigator.canShare) {
        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const blob = await response.blob();
            const file = new File([blob], 'serdal-review.jpg', { type: 'image/jpeg' });

            if (navigator.canShare({ files: [file] })) {
                await navigator.share({ files: [file] });
                return 'shared';
            }
        } catch (error) {
            if (error.name === 'AbortError') return 'cancelled'; // пользователь закрыл окно «Поделиться»
        }
    }

    window.location.href = url; // компьютер и запасной вариант: скачать файл
    return 'download';
};
