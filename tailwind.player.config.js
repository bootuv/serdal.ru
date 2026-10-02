/**
 * Tailwind для плеера записей на публичных страницах (видео в статьях блога): токены кабинетов,
 * но классы — только из разметки плеера и иконок, без сброса стилей (preflight) — сайт живет на своих стилях.
 */
import cabinet from './tailwind.cabinet.config.js';

export default {
    ...cabinet,
    content: [
        './resources/views/components/ui/video-player.blade.php',
        './resources/views/components/ui/icon.blade.php',
    ],
    corePlugins: { ...(cabinet.corePlugins || {}), preflight: false },
};
