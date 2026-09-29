/**
 * Tailwind для кабинетов учителя и ученика.
 * Шкалы ЗАМЕНЯЮТ стандартные (не extend): классы вне системы просто не существуют.
 * Правила и роли токенов — docs/design/BRAND.md.
 */

// Размеры элементов (ширина/высота): иконки, кнопки, аватары, сайдбар
const sizes = {
    0: '0px',
    px: '1px',
    1: '4px',
    2: '8px',
    3: '12px',
    4: '16px',   // иконка в тексте
    5: '20px',   // иконка в меню и кнопках, счётчик
    6: '24px',   // бейдж, чекбокс
    8: '32px',
    9: '36px',   // кнопка S
    10: '40px',  // аватар, поле в плотной форме
    11: '44px',  // кнопка M, пункт меню, поле
    12: '48px',  // плитка даты
    13: '52px',  // кнопка L
    16: '64px',  // аватар в шапке, иконка пустого состояния
    20: '80px',  // аватар учителя в карточке «Как вам занятия?»
    40: '160px', // кольца успеваемости (узкие)
    44: '176px', // кольца успеваемости
    full: '100%',
    auto: 'auto',
    sidebar: '264px',
    dialogs: '360px', // колонка диалогов в «Сообщениях»
    bubble: '480px',  // ширина сообщения в чате
    drawer: '440px',  // боковая панель (уведомления)
    tour: '400px',    // карточка шага тура рядом с подсвеченным элементом
    'auth-form': '440px', // форма на экранах входа
    screen: '100vh',  // сайдбар и экран «Сообщения» на компьютере
    'modal-s': '480px',
    'modal-m': '640px',
    'modal-l': '960px',
    form: '640px',
};

export default {
    content: [
        './resources/views/components/ui/**/*.blade.php',
        './resources/views/components/layouts/cabinet*.blade.php',
        './resources/views/components/layouts/auth.blade.php',
        './resources/views/errors/**/*.blade.php',
        './resources/views/livewire/cabinet/**/*.blade.php',
        './resources/views/livewire/auth/**/*.blade.php',
        './app/Livewire/Cabinet/**/*.php',
    ],
    theme: {
        colors: {
            transparent: 'transparent',
            current: 'currentColor',
            white: '#FFFFFF',
            brand: { DEFAULT: '#FFE500', hover: '#F2D900' },
            ink: { DEFAULT: '#202323', hover: '#3A3D3D' },
            muted: '#5F6262',
            faint: '#B5B8B8',
            mint: { DEFAULT: '#EBF5F4', 2: '#E3EFEE' },
            soft: { DEFAULT: '#F1F5F5', hover: '#F4F9F8' },
            line: { DEFAULT: '#2023231F', strong: '#20232340' },
            danger: { DEFAULT: '#DC2626', bg: '#FDE7E4', fg: '#A3261B' },
            ok: { bg: '#D3F8F3', fg: '#0E5A53' },
            promo: '#FFFBD6',
            // news — фон карточки важной новости и «Как вам занятия?»; news-2 — тот же тон темнее: пустые звёзды на ней
            news: { DEFAULT: '#E8F0FE', 2: '#B4CBF3' },
            folder: '#F2B200', // заливной значок папки в материалах (плитка — нейтральная, как у файлов)
            star: '#FFA41C',
            av: { 1: '#FFEC70', 2: '#C2DCFF', 3: '#DDD0FF' },
            chart: { 1: '#2A78D6', 2: '#1BAF7A', 3: '#EDA100' },
            pen: { red: '#DC2626', blue: '#2A78D6', green: '#1BAF7A' }, // карандаш пометок на фото работ
            scrim: '#202323B8',
        },
        spacing: {
            0: '0px',
            px: '1px',
            1: '4px',
            2: '8px',
            3: '12px',
            4: '16px',
            6: '24px',
            8: '32px',
            12: '48px',
            tabbar: '96px', // запас под нижнюю панель вкладок на телефоне
        },
        borderRadius: {
            none: '0px',
            sm: '8px',
            DEFAULT: '12px',
            lg: '16px',
            xl: '24px',
            full: '9999px',
        },
        fontFamily: {
            sans: ['Inter', 'system-ui', 'sans-serif'],
        },
        fontWeight: {
            normal: '400',
            medium: '500',
            semibold: '600',
        },
        fontSize: {
            h1: ['36px', { lineHeight: '44px', letterSpacing: '-0.8px' }],
            'h1-m': ['28px', { lineHeight: '36px', letterSpacing: '-0.5px' }],
            h2: ['20px', { lineHeight: '28px', letterSpacing: '-0.2px' }],
            num: ['28px', { lineHeight: '36px', letterSpacing: '-0.5px' }],
            t1: ['16px', { lineHeight: '24px' }],
            't1-s': ['15px', { lineHeight: '20px' }],
            t2: ['14px', { lineHeight: '20px' }],
            t3: ['13px', { lineHeight: '16px' }],
            count: ['12px', { lineHeight: '16px' }],
            tab: ['11px', { lineHeight: '16px' }],
        },
        boxShadow: {
            none: 'none',
            card: '0 8px 24px #2322150F',
            modal: '0 24px 60px #00000040',
            seg: '0 1px 2px #2023231A',
            outline: 'inset 0 0 0 1px #20232340',
            'outline-ink': 'inset 0 0 0 1px #202323',
            line: 'inset 0 0 0 1px #2023231F',       // вариант выбора (x-ui.option)
            selected: 'inset 0 0 0 1.5px #202323',   // выбранный вариант, выбранный человек
            radio: 'inset 0 0 0 6px #202323',        // отмеченная радиокнопка
            'dot-ring': '0 0 0 2px #FFFFFF',
            spot: '0 0 0 9999px #202323B8',          // затемнение вокруг подсвеченного элемента в туре (цвет scrim)
        },
        width: sizes,
        height: sizes,
        size: sizes,
        minWidth: sizes,
        minHeight: sizes,
        maxHeight: { ...sizes, none: 'none' }, // иначе max-h-* берёт урезанную шкалу отступов (не было max-h-44 у картинок в чате)
        maxWidth: { ...sizes, none: 'none', text: '640px' },
        extend: {},
    },
    plugins: [],
};
