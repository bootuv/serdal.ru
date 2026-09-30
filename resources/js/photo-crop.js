/**
 * Обрезка фото профиля перед загрузкой (resources/views/components/ui/photo-crop.blade.php).
 * Выбранный файл не уходит на сервер сразу: открывается окно, где фото двигают (мышь, палец, стрелки),
 * увеличивают (ползунок, колёсико, щипок, + и −) и поворачивают на 90°. «Готово» — собираем квадрат на холсте
 * и отдаём его Livewire как обычную загрузку ($wire.upload) в свойство model. Сервер получает уже готовый JPG.
 *
 * Положение хранится в пикселях исходного фото (после поворота): cx, cy — центр рамки от центра фото,
 * zoom — увеличение от «фото вписано в рамку по короткой стороне». Так оно не зависит от размера окна.
 * Цвета холста — токены soft и scrim (canvas не читает классы).
 */
const MAX_ZOOM = 4;
const MIN_CROP = 200;   // меньше этого куска исходника в рамку не берём: дальше только мыло
const OUTPUT = 1024;    // сторона готового квадрата; сервер сам уменьшит до своих размеров
const PAD = 24;         // поле вокруг рамки на холсте
const BG = '#F1F5F5';
const SHADE = 'rgba(32, 35, 35, 0.72)';

function registerPhotoCrop(Alpine) {
    Alpine.data('photoCrop', (model = 'photo') => {
        // Вне реактивных данных: Alpine не должен оборачивать картинку и указатели
        let img = null;
        let url = null;
        let pinch = null;
        const pointers = new Map();

        return {
            open: false,
            busy: false,
            failed: false,
            pressed: false, // нажатие началось на затемнении — отпускание там же закрывает окно
            zoom: 1,
            maxZoom: MAX_ZOOM,
            turn: 0, // четверти оборота по часовой
            cx: 0,
            cy: 0,

            init() {
                // Холст получает размер, когда окно показано (x-show открывает его не сразу) и когда меняется экран
                new ResizeObserver(() => this.draw()).observe(this.$refs.stage);
            },

            pick(e) {
                const file = e.target.files[0];
                e.target.value = ''; // тот же файл можно выбрать ещё раз
                if (!file) return;

                const next = URL.createObjectURL(file);
                const image = new Image();
                image.onload = () => {
                    this.release();
                    img = image;
                    url = next;
                    this.zoom = 1;
                    this.turn = 0;
                    this.cx = 0;
                    this.cy = 0;
                    this.maxZoom = Math.max(1, Math.min(MAX_ZOOM, this.side() / MIN_CROP));
                    this.failed = false;
                    this.open = true;
                    this.$nextTick(() => this.draw());
                };
                image.onerror = () => {
                    URL.revokeObjectURL(next);
                    this.$dispatch('toast', { message: 'Не удалось открыть файл. Выберите фото в формате JPG или PNG', tone: 'danger' });
                };
                image.src = next;
            },

            close() {
                if (this.busy) this.$wire.cancelUpload(model);
                this.busy = false;
                this.open = false;
                this.release();
            },

            release() {
                if (url) URL.revokeObjectURL(url);
                img = null;
                url = null;
                pinch = null;
                pointers.clear();
            },

            // Размеры фото с учётом поворота и его короткая сторона
            width() { return this.turn % 2 ? img.naturalHeight : img.naturalWidth; },
            height() { return this.turn % 2 ? img.naturalWidth : img.naturalHeight; },
            side() { return Math.min(img.naturalWidth, img.naturalHeight); },

            // Рамка не выходит за края фото
            clamp() {
                this.zoom = Math.min(this.maxZoom, Math.max(1, this.zoom));
                const view = this.side() / this.zoom;
                const mx = (this.width() - view) / 2;
                const my = (this.height() - view) / 2;
                this.cx = Math.min(mx, Math.max(-mx, this.cx));
                this.cy = Math.min(my, Math.max(-my, this.cy));
            },

            // Фото на холсте: center — центр рамки, frame — её сторона
            paint(ctx, center, frame) {
                const k = frame * this.zoom / this.side();
                ctx.save();
                ctx.translate(center - this.cx * k, center - this.cy * k);
                ctx.rotate(this.turn * Math.PI / 2);
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(img, -img.naturalWidth * k / 2, -img.naturalHeight * k / 2, img.naturalWidth * k, img.naturalHeight * k);
                ctx.restore();
            },

            frame() { return this.$refs.stage.clientWidth - PAD * 2; },

            draw() {
                const stage = this.$refs.stage;
                const size = stage.clientWidth;
                if (!img || !size) return;
                this.clamp();

                const ratio = window.devicePixelRatio || 1;
                if (stage.width !== Math.round(size * ratio)) {
                    stage.width = stage.height = Math.round(size * ratio);
                }

                const ctx = stage.getContext('2d');
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                ctx.fillStyle = BG;
                ctx.fillRect(0, 0, size, size);
                this.paint(ctx, size / 2, this.frame());

                // Затемнение вокруг рамки; углы скруглены, как у фото в кабинете
                const frame = this.frame();
                ctx.beginPath();
                ctx.rect(0, 0, size, size);
                if (ctx.roundRect) ctx.roundRect(PAD, PAD, frame, frame, frame / 4);
                else ctx.rect(PAD, PAD, frame, frame);
                ctx.fillStyle = SHADE;
                ctx.fill('evenodd');
            },

            setZoom(value) {
                this.zoom = Number(value);
                this.draw();
            },

            zoomBy(factor) {
                this.setZoom(this.zoom * factor);
            },

            rotate() {
                // Четверть оборота по часовой: точка (x, y) переходит в (−y, x)
                [this.cx, this.cy] = [-this.cy, this.cx];
                this.turn = (this.turn + 1) % 4;
                this.draw();
            },

            shift(dx, dy) {
                const k = this.frame() * this.zoom / this.side();
                this.cx -= dx / k;
                this.cy -= dy / k;
                this.draw();
            },

            down(e) {
                this.$refs.stage.setPointerCapture(e.pointerId);
                pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
                pinch = pointers.size === 2 ? { gap: this.gap(), zoom: this.zoom } : null;
            },

            move(e) {
                const last = pointers.get(e.pointerId);
                if (!last) return;
                pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

                if (pinch) {
                    this.setZoom(pinch.zoom * this.gap() / pinch.gap);
                } else if (pointers.size === 1) {
                    this.shift(e.clientX - last.x, e.clientY - last.y);
                }
            },

            up(e) {
                pointers.delete(e.pointerId);
                pinch = null;
            },

            // Расстояние между двумя пальцами
            gap() {
                const [a, b] = [...pointers.values()];
                return Math.hypot(a.x - b.x, a.y - b.y) || 1;
            },

            // Колёсико и щипок на тачпаде (в Chrome и Firefox щипок приходит как прокрутка с Ctrl)
            wheel(e) {
                this.zoomBy(Math.exp(-e.deltaY * (e.ctrlKey ? 0.01 : 0.002)));
            },

            // Щипок на тачпаде в Safari
            gestureStart() {
                pinch = { gap: 1, zoom: this.zoom, gesture: true };
            },

            gestureChange(e) {
                if (pinch?.gesture) this.setZoom(pinch.zoom * e.scale);
            },

            key(e) {
                const step = e.shiftKey ? 48 : 16;
                const actions = {
                    ArrowLeft: () => this.shift(step, 0),
                    ArrowRight: () => this.shift(-step, 0),
                    ArrowUp: () => this.shift(0, step),
                    ArrowDown: () => this.shift(0, -step),
                    '+': () => this.zoomBy(1.1),
                    '=': () => this.zoomBy(1.1),
                    '-': () => this.zoomBy(1 / 1.1),
                };
                if (actions[e.key] && !e.metaKey && !e.ctrlKey && !e.altKey) {
                    e.preventDefault();
                    actions[e.key]();
                }
            },

            apply() {
                if (!img || this.busy) return;
                this.clamp();
                this.busy = true;
                this.failed = false;

                const size = Math.max(1, Math.min(OUTPUT, Math.round(this.side() / this.zoom)));
                const out = document.createElement('canvas');
                out.width = out.height = size;
                const ctx = out.getContext('2d');
                ctx.fillStyle = '#FFFFFF'; // прозрачные места PNG в JPG стали бы чёрными
                ctx.fillRect(0, 0, size, size);
                this.paint(ctx, size / 2, size);

                out.toBlob((blob) => {
                    if (!blob || !this.busy) {
                        this.failed = this.busy;
                        this.busy = false;
                        return;
                    }
                    this.$wire.upload(
                        model,
                        new File([blob], 'photo.jpg', { type: 'image/jpeg' }),
                        () => { this.busy = false; this.close(); },
                        () => { this.busy = false; this.failed = true; },
                    );
                }, 'image/jpeg', 0.92);
            },
        };
    });
}

if (window.Alpine) {
    registerPhotoCrop(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerPhotoCrop(window.Alpine));
}
