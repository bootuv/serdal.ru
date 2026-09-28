/**
 * Тур по кабинету (livewire/cabinet/tour.blade.php, шаги — App\Services\CabinetTourService).
 *
 * Шаг с url показывается на своём экране: если он на другом — переходим туда, номер шага храним в sessionStorage
 * и продолжаем после загрузки. Шаг без url — там, где человек сейчас (пункты меню, колокольчик, профиль).
 * Подсветка — «окно» в затемнении вокруг первого видимого элемента [data-tour~=…] из targets шага;
 * не нашли — окно по центру. На компьютере карточка встаёт рядом с элементом, на телефоне — над нижней панелью.
 * Запуск заново — ссылка «Тур по кабинету» (?tour=1).
 *
 * Без рывков при переходах: скрипт в <head> раскладки затемняет страницу ещё до загрузки (html[data-tour=loading],
 * cabinet.css), смена экранов идёт плавно (view transitions), карточка проявляется, когда уже встала на место,
 * а подсветка перелетает с элемента на элемент только в пределах одного экрана.
 */
const KEY = 'cabinet-tour';
const GAP = 16;  // от элемента до карточки и от карточки до края экрана
const PAD = 4;   // запас подсветки вокруг элемента
const DESKTOP = 1024; // lg: с этой ширины есть сайдбар

function read() {
    try {
        return JSON.parse(sessionStorage.getItem(KEY) || 'null');
    } catch {
        return null;
    }
}

function write(value) {
    try {
        value === null ? sessionStorage.removeItem(KEY) : sessionStorage.setItem(KEY, JSON.stringify(value));
    } catch {
        // приватный режим без хранилища: тур работает в пределах экрана
    }
}

function samePage(url) {
    return url === null || new URL(url, location.href).pathname === location.pathname;
}

function visible(el) {
    if (!el.getClientRects().length) return false;
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden';
}

function setDim(state) {
    if (state) document.documentElement.dataset.tour = state;
    else delete document.documentElement.dataset.tour;
}

// Плавная смена экрана — только во время тура; обычные переходы по кабинету как были
window.addEventListener('pageswap', (e) => {
    if (e.viewTransition && !read()) e.viewTransition.skipTransition();
});

function registerTour(Alpine) {
    Alpine.data('cabinetTour', (steps, auto, hint) => ({
        steps,
        i: null,
        mode: 'center', // center — окно по центру; dock — над нижней панелью (телефон); float — рядом с элементом
        spot: false,
        ready: false,   // карточка на месте — можно показать
        animate: false, // перелёт подсветки и карточки (не на первом показе экрана)
        frame: null,
        placed: null,   // прошлые координаты: не двигаем без нужды

        get step() { return this.steps[this.i] ?? this.steps[0]; },
        get open() { return this.i !== null; },
        get isFirst() { return this.i === 0; },
        get isLast() { return this.i === this.steps.length - 1; },
        get counter() { return this.isFirst || this.isLast ? '' : `${this.i} из ${this.steps.length - 2}`; },

        init() {
            const url = new URL(location.href);
            if (url.searchParams.has('tour')) {
                url.searchParams.delete('tour');
                history.replaceState(history.state, '', url);
                return this.go(1);
            }

            const saved = read();
            if (saved && this.steps[saved.i] && samePage(this.steps[saved.i].url)) return this.go(saved.i);
            write(null);
            setDim(null);

            if (auto) {
                setDim('on'); // окно «Включить уведомления?» подождёт
                setTimeout(() => this.go(0), 600);
            }
        },

        go(i) {
            const step = this.steps[i];
            if (!step) return this.close(false);
            write({ i });

            if (!samePage(step.url)) {
                this.ready = false; // карточка гаснет, затемнение остаётся до нового экрана
                setTimeout(() => { window.location.href = step.url; }, 150);
                return;
            }

            this.animate = this.open && this.ready;
            this.ready = this.animate; // на новом экране карточка проявится, когда встанет на место
            this.i = i;
            this.placed = null;
            // Раскрываем после того, как клик «Далее» дойдёт до документа, — иначе меню тут же закроется (click.outside)
            setTimeout(() => {
                this.reveal(step.reveal ?? null);
                this.$nextTick(() => this.place(true));
                // шрифты и картинки могут сдвинуть вёрстку после загрузки — поправим, если элемент сдвинулся
                setTimeout(() => this.place(), 400);
                document.fonts?.ready.then(() => this.place());
            }, 0);
        },

        next() { this.isLast ? this.close(false) : this.go(this.i + 1); },
        prev() { if (this.i > 1) this.go(this.i - 1); },

        /** Закрыть; remind — тостом напомнить, где тур найти снова. */
        close(remind = true) {
            const wasOpen = this.open;
            this.i = null;
            this.ready = false;
            write(null);
            setDim(null);
            this.reveal(null);
            this.$wire.seen();
            if (remind && wasOpen) this.$dispatch('toast', { message: innerWidth < DESKTOP ? hint.phone : hint.desktop });
        },

        /** Раскрыть на время шага (меню «Помощь» в сайдбаре) или свернуть обратно. */
        reveal(name) {
            window.dispatchEvent(new CustomEvent('tour-reveal', { detail: { name } }));
        },

        key(e) {
            if (!this.open) return;
            if (e.key === 'Escape') this.close();
            else if (e.key === 'ArrowRight') this.next();
            else if (e.key === 'ArrowLeft') this.prev();
        },

        target() {
            for (const name of this.step.targets) {
                const el = [...document.querySelectorAll(`[data-tour~="${name}"]`)].find(visible);
                if (el) return el;
            }
            return null;
        },

        /** Подсветка и место карточки; scroll — прокрутить к элементу, если он за краем экрана. */
        place(scroll = false) {
            if (!this.open) return;
            cancelAnimationFrame(this.frame);
            this.frame = requestAnimationFrame(() => {
                const el = this.target();
                let r = el?.getBoundingClientRect() ?? null;
                if (el && scroll && (r.top < GAP || r.bottom > innerHeight - GAP)) {
                    el.scrollIntoView({ block: 'center' });
                    r = el.getBoundingClientRect();
                }

                const key = r ? [r.left, r.top, r.width, r.height, innerWidth, innerHeight].map(Math.round).join() : `none,${innerWidth}`;
                if (key === this.placed) return;
                this.placed = key;

                const box = this.$refs.box;
                if (!r) {
                    this.spot = false;
                    this.mode = 'center';
                    box.style.left = box.style.top = '';
                    return this.show();
                }

                const spot = this.$refs.spot;
                spot.style.left = `${r.left - PAD}px`;
                spot.style.top = `${r.top - PAD}px`;
                spot.style.width = `${r.width + PAD * 2}px`;
                spot.style.height = `${r.height + PAD * 2}px`;
                this.spot = true;

                if (innerWidth < DESKTOP) {
                    this.mode = 'dock';
                    box.style.left = box.style.top = '';
                    return this.show();
                }

                this.mode = 'float';
                this.$nextTick(() => {
                    const w = box.offsetWidth;
                    const h = box.offsetHeight;
                    const clampX = (x) => Math.min(Math.max(x, GAP), innerWidth - w - GAP);
                    const clampY = (y) => Math.min(Math.max(y, GAP), innerHeight - h - GAP);
                    let x;
                    let y;
                    if (r.right + PAD + GAP + w <= innerWidth - GAP) {        // справа (пункты меню)
                        x = r.right + PAD + GAP;
                        y = clampY(r.top + r.height / 2 - h / 2);
                    } else if (r.bottom + PAD + GAP + h <= innerHeight - GAP) { // снизу (кнопки в шапке)
                        x = clampX(r.right - w);
                        y = r.bottom + PAD + GAP;
                    } else if (r.top - PAD - GAP - h >= GAP) {                  // сверху
                        x = clampX(r.right - w);
                        y = r.top - PAD - GAP - h;
                    } else {                                                    // слева
                        x = clampX(r.left - PAD - GAP - w);
                        y = clampY(r.top + r.height / 2 - h / 2);
                    }
                    box.style.left = `${x}px`;
                    box.style.top = `${y}px`;
                    this.show();
                });
            });
        },

        /** Карточка на месте: убрать затемнение загрузки, проявить карточку, дальше — с перелётом. */
        show() {
            setDim('on');
            requestAnimationFrame(() => {
                this.ready = true;
                this.animate = true;
                if (!this.$el.contains(document.activeElement)) this.$refs.primary?.focus({ preventScroll: true });
            });
        },
    }));
}

if (window.Alpine) {
    registerTour(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerTour(window.Alpine));
}
