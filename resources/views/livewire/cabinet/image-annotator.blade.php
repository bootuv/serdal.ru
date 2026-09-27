{{-- Холст пометок нового кабинета (App\Livewire\ImageAnnotator). Стоит внутри x-ui.modal родителя (fill: окно на всю высоту);
     кнопка «Сохранить пометки» в подвале окна шлёт событие annotator-save. Сохранение — saveAnnotatedImage (как в старом кабинете).
     Закрыть окно или открыть другое фото родитель просит событием annotator-leave ({ photo: номер } или без него):
     если есть несохранённые пометки — сначала спрашиваем здесь же. Закрытие вкладки — предупреждение браузера.
     Рабочая область как в Figma: панель инструментов закреплена, фото лежит на холсте. Прокрутка и два пальца на тачпаде двигают фото
     (Shift — по горизонтали), щипок и Ctrl/⌘ + прокрутка масштабируют к курсору, пробел или средняя кнопка мыши — временная рука,
     H — рука, P — карандаш, Shift+1 — фото целиком, Shift+0 — 100%, Ctrl/⌘+Z — отменить. На планшете два пальца двигают и масштабируют.
     Цвета пера — токены pen-* (в скрипте те же значения: canvas не читает классы). --}}
@php
    $tool = 'flex size-9 items-center justify-center rounded-sm text-muted hover:text-ink';
@endphp
<div class="flex min-h-0 min-w-0 flex-1 flex-col gap-4" x-data="cabinetAnnotator(@js($imageUrl))" x-on:annotator-save.window="save()"
     x-on:annotator-leave.window="leave($event.detail)" x-on:beforeunload.window="if (dirty()) { $event.preventDefault(); $event.returnValue = ''; }"
     x-on:keydown.window="keyDown($event)" x-on:keyup.window="keyUp($event)" x-on:blur.window="space = false">
    <div x-show="pending" x-cloak class="flex shrink-0 flex-wrap items-center gap-3 rounded-lg bg-soft px-4 py-3" role="alert">
        <span class="min-w-0 flex-1 text-t2 font-medium">Пометки на этом фото не сохранены</span>
        <x-ui.btn size="s" x-on:click="discard()">Не сохранять</x-ui.btn>
        <button type="button" class="link text-t2" x-on:click="pending = null">Вернуться к пометкам</button>
    </div>
    @if ($imageUrl)
        <div class="flex shrink-0 flex-wrap items-center gap-2" role="toolbar" aria-label="Инструменты">
            <div class="flex gap-1 rounded bg-soft p-1" role="group" aria-label="Инструмент">
                <button type="button" class="{{ $tool }}" x-bind:class="pan ? '' : 'bg-white text-ink shadow-seg'" x-bind:aria-pressed="pan ? 'false' : 'true'" x-on:click="pan = false" aria-label="Карандаш (P)"><x-ui.icon name="pencil" /></button>
                <button type="button" class="{{ $tool }}" x-bind:class="pan ? 'bg-white text-ink shadow-seg' : ''" x-bind:aria-pressed="pan ? 'true' : 'false'" x-on:click="pan = true" aria-label="Перемещение (H или пробел)"><x-ui.icon name="move" /></button>
            </div>
            <div class="flex gap-1 rounded bg-soft p-1" role="group" aria-label="Цвет">
                <button type="button" class="{{ $tool }}" x-bind:class="color === colors.red ? 'bg-white shadow-seg' : ''" x-bind:aria-pressed="color === colors.red ? 'true' : 'false'" x-on:click="color = colors.red; pan = false" aria-label="Красный"><span class="size-4 rounded-full bg-pen-red"></span></button>
                <button type="button" class="{{ $tool }}" x-bind:class="color === colors.blue ? 'bg-white shadow-seg' : ''" x-bind:aria-pressed="color === colors.blue ? 'true' : 'false'" x-on:click="color = colors.blue; pan = false" aria-label="Синий"><span class="size-4 rounded-full bg-pen-blue"></span></button>
                <button type="button" class="{{ $tool }}" x-bind:class="color === colors.green ? 'bg-white shadow-seg' : ''" x-bind:aria-pressed="color === colors.green ? 'true' : 'false'" x-on:click="color = colors.green; pan = false" aria-label="Зелёный"><span class="size-4 rounded-full bg-pen-green"></span></button>
            </div>
            <div class="flex gap-1 rounded bg-soft p-1" role="group" aria-label="Действия с фото">
                <button type="button" class="{{ $tool }}" x-on:click="undo()" aria-label="Отменить последнюю пометку"><x-ui.icon name="undo" /></button>
                <button type="button" class="{{ $tool }}" x-on:click="clear()" aria-label="Стереть все пометки"><x-ui.icon name="trash" /></button>
                <button type="button" class="{{ $tool }}" x-on:click="rotate()" aria-label="Повернуть фото"><x-ui.icon name="rotate" /></button>
            </div>
            <div class="flex gap-1 rounded bg-soft p-1" role="group" aria-label="Масштаб">
                <button type="button" class="{{ $tool }}" x-on:click="zoomBy(1 / 1.5)" aria-label="Уменьшить"><x-ui.icon name="zoom-out" /></button>
                <button type="button" class="flex h-9 min-w-12 items-center justify-center rounded-sm px-2 text-t2 font-medium tabular-nums text-muted hover:text-ink"
                        x-on:click="fitToStage()" x-text="percent()" aria-label="Показать фото целиком"></button>
                <button type="button" class="{{ $tool }}" x-on:click="zoomBy(1.5)" aria-label="Увеличить"><x-ui.icon name="zoom-in" /></button>
            </div>
            <span class="text-t2 text-muted" wire:loading wire:target="saveAnnotatedImage">Сохраняем…</span>
        </div>

        {{-- Холст: фото двигается и масштабируется внутри, сама область не прокручивается --}}
        <div x-ref="stage" class="relative min-h-40 flex-1 touch-none select-none overflow-hidden rounded-lg bg-soft" x-bind:class="cursor()"
             x-on:pointerdown="down($event)" x-on:pointermove="move($event)" x-on:pointerup="up($event)" x-on:pointercancel="up($event)"
             x-on:wheel.prevent="wheel($event)" x-on:gesturestart.prevent="gestureStart($event)" x-on:gesturechange.prevent="gestureChange($event)"
             x-on:mousedown="if ($event.button === 1) $event.preventDefault()" x-on:contextmenu.prevent>
            <canvas x-ref="canvas" class="absolute left-0 top-0 block max-w-none origin-top-left shadow-card"></canvas>
            <p x-show="failed" x-cloak class="absolute inset-0 m-auto flex items-center justify-center p-6 text-center text-t2 text-muted">Не удалось загрузить фото — закройте окно и попробуйте ещё раз.</p>
        </div>
    @else
        <p class="text-t2 text-muted">Это фото нельзя открыть для пометок.</p>
    @endif
</div>

@script
<script>
    Alpine.data('cabinetAnnotator', (url) => ({
        colors: { red: '#DC2626', blue: '#2A78D6', green: '#1BAF7A' },
        color: '#DC2626',
        pan: false,          // выбрана рука
        space: false,        // зажат пробел — временная рука
        canvas: null,
        ctx: null,
        view: { s: 1, x: 0, y: 0 }, // масштаб (экранных точек на пиксель фото) и сдвиг фото внутри холста
        touched: false,      // масштаб или сдвиг меняли руками — при изменении размера окна не подгоняем заново
        history: [],
        drawing: false,
        last: null,
        drag: null,
        pointers: {},        // активные касания: два пальца — перемещение и масштаб
        pinch: null,
        gesture: null,
        failed: false,
        rotated: false,
        pending: null,

        init() {
            if (!url) return;
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => {
                const c = this.$refs.canvas;
                c.width = img.naturalWidth;
                c.height = img.naturalHeight;
                this.ctx = c.getContext('2d', { willReadFrequently: true });
                this.ctx.drawImage(img, 0, 0);
                this.canvas = c;
                this.history = [this.snapshot()];
                this.fitToStage();
                this.observer = new ResizeObserver(() => this.touched ? this.place(this.view.s, this.view.x, this.view.y) : this.fitToStage());
                this.observer.observe(this.$refs.stage);
            };
            img.onerror = () => { this.failed = true; };
            img.src = url;
        },

        destroy() {
            if (this.observer) this.observer.disconnect();
        },

        snapshot() {
            return this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        },

        hand() {
            return this.pan || this.space;
        },

        cursor() {
            if (this.drag) return 'cursor-grabbing';
            return this.hand() ? 'cursor-grab' : 'cursor-crosshair';
        },

        percent() {
            return Math.round(this.view.s * 100) + '%';
        },

        limits() {
            const fit = this.fitScale();
            return { min: Math.min(fit, 1) / 2, max: Math.max(fit, 8) };
        },

        // Масштаб «фото целиком» с полями 24 по краям
        fitScale() {
            const st = this.$refs.stage;
            const w = Math.max(1, st.clientWidth - 48), h = Math.max(1, st.clientHeight - 48);
            return Math.min(w / this.canvas.width, h / this.canvas.height);
        },

        fitToStage() {
            if (!this.canvas) return;
            const st = this.$refs.stage;
            const s = this.fitScale();
            this.touched = false;
            this.place(s, (st.clientWidth - this.canvas.width * s) / 2, (st.clientHeight - this.canvas.height * s) / 2);
        },

        // Ставит фото; хотя бы 48 точек фото остаются в холсте, чтобы его нельзя было потерять
        place(s, x, y) {
            const st = this.$refs.stage, m = 48;
            const w = this.canvas.width * s, h = this.canvas.height * s;
            x = Math.min(st.clientWidth - m, Math.max(m - w, x));
            y = Math.min(st.clientHeight - m, Math.max(m - h, y));
            this.view = { s, x, y };
            this.canvas.style.width = w + 'px';
            this.canvas.style.height = h + 'px';
            this.canvas.style.transform = 'translate(' + x + 'px, ' + y + 'px)';
        },

        // Масштаб к точке холста (cx, cy): то, что под ней, остаётся на месте
        zoomAt(s, cx, cy) {
            if (!this.canvas) return;
            const { min, max } = this.limits();
            s = Math.min(max, Math.max(min, s));
            const k = s / this.view.s;
            this.touched = true;
            this.place(s, cx - (cx - this.view.x) * k, cy - (cy - this.view.y) * k);
        },

        zoomBy(f) {
            if (!this.canvas) return;
            const st = this.$refs.stage;
            this.zoomAt(this.view.s * f, st.clientWidth / 2, st.clientHeight / 2);
        },

        panBy(dx, dy) {
            if (!this.canvas) return;
            this.touched = true;
            this.place(this.view.s, this.view.x + dx, this.view.y + dy);
        },

        local(e) {
            const r = this.$refs.stage.getBoundingClientRect();
            return { x: e.clientX - r.left, y: e.clientY - r.top };
        },

        // Точка на фото в его пикселях
        pos(e) {
            const p = this.local(e);
            return { x: (p.x - this.view.x) / this.view.s, y: (p.y - this.view.y) / this.view.s };
        },

        // Прокрутка двигает фото, щипок и Ctrl/⌘ + прокрутка масштабируют (как в Figma)
        wheel(e) {
            if (!this.canvas) return;
            const unit = e.deltaMode === 1 ? 16 : e.deltaMode === 2 ? this.$refs.stage.clientHeight : 1;
            let dx = e.deltaX * unit, dy = e.deltaY * unit;
            if (e.ctrlKey || e.metaKey) {
                const p = this.local(e);
                const d = Math.max(-50, Math.min(50, dy));
                this.zoomAt(this.view.s * Math.exp(-d * 0.01), p.x, p.y);
                return;
            }
            if (e.shiftKey && !dx) [dx, dy] = [dy, 0];
            this.panBy(-dx, -dy);
        },

        // Щипок на тачпаде в Safari приходит жестом, а не прокруткой
        gestureStart(e) {
            this.gesture = this.view.s;
        },

        gestureChange(e) {
            if (!this.canvas || this.gesture === null) return;
            const p = this.local(e);
            this.zoomAt(this.gesture * e.scale, p.x, p.y);
        },

        down(e) {
            if (!this.canvas) return;
            this.$refs.stage.setPointerCapture(e.pointerId);
            this.pointers[e.pointerId] = this.local(e);
            const ids = Object.keys(this.pointers);
            if (ids.length === 2) {
                // Второй палец: начатая первым линия — не пометка, а начало жеста
                if (this.drawing) {
                    this.drawing = false;
                    this.ctx.putImageData(this.history[this.history.length - 1], 0, 0);
                }
                this.drag = null;
                const [a, b] = ids.map((id) => this.pointers[id]);
                const mid = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
                this.pinch = {
                    d: Math.hypot(a.x - b.x, a.y - b.y) || 1,
                    s: this.view.s,
                    px: (mid.x - this.view.x) / this.view.s,
                    py: (mid.y - this.view.y) / this.view.s,
                };
                return;
            }
            if (ids.length > 2) return;
            if (this.hand() || e.button === 1) {
                this.drag = { x: e.clientX, y: e.clientY, vx: this.view.x, vy: this.view.y };
                return;
            }
            if (e.button !== 0) return;
            const p = this.pos(e);
            if (p.x < 0 || p.y < 0 || p.x > this.canvas.width || p.y > this.canvas.height) return;
            this.drawing = true;
            this.last = p;
            this.stroke(p);
        },

        move(e) {
            if (this.pointers[e.pointerId]) this.pointers[e.pointerId] = this.local(e);
            if (this.pinch) {
                const ids = Object.keys(this.pointers);
                if (ids.length < 2) return;
                const [a, b] = ids.slice(0, 2).map((id) => this.pointers[id]);
                const mid = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
                const { min, max } = this.limits();
                const s = Math.min(max, Math.max(min, this.pinch.s * Math.hypot(a.x - b.x, a.y - b.y) / this.pinch.d));
                this.touched = true;
                this.place(s, mid.x - this.pinch.px * s, mid.y - this.pinch.py * s);
                return;
            }
            if (this.drag) {
                this.touched = true;
                this.place(this.view.s, this.drag.vx + e.clientX - this.drag.x, this.drag.vy + e.clientY - this.drag.y);
                return;
            }
            if (!this.drawing) return;
            const events = e.getCoalescedEvents ? e.getCoalescedEvents() : [];
            for (const ev of (events.length ? events : [e])) {
                const p = this.pos(ev);
                this.stroke(p);
                this.last = p;
            }
        },

        // Линия 4 px на экране при любом масштабе
        stroke(p) {
            const ctx = this.ctx;
            ctx.strokeStyle = this.color;
            ctx.lineWidth = 4 / this.view.s;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.beginPath();
            ctx.moveTo(this.last.x, this.last.y);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
        },

        up(e) {
            delete this.pointers[e.pointerId];
            if (this.pinch) {
                if (Object.keys(this.pointers).length < 2) this.pinch = null;
                return;
            }
            if (this.drawing) {
                this.drawing = false;
                this.history.push(this.snapshot());
                if (this.history.length > 21) this.history.splice(1, 1);
            }
            this.drag = null;
        },

        // Клавиши как в Figma. В полях ввода не перехватываем
        keyDown(e) {
            if (!this.canvas || e.target.closest('input, textarea, select, [contenteditable]')) return;
            const mod = e.ctrlKey || e.metaKey;
            if (e.code === 'Space') {
                e.preventDefault();
                this.space = true;
            } else if (mod && e.code === 'KeyZ' && !e.shiftKey) {
                e.preventDefault();
                this.undo();
            } else if (mod && (e.key === '=' || e.key === '+')) {
                e.preventDefault();
                this.zoomBy(1.5);
            } else if (mod && e.key === '-') {
                e.preventDefault();
                this.zoomBy(1 / 1.5);
            } else if ((mod || e.shiftKey) && e.code === 'Digit0') {
                e.preventDefault();
                this.zoomBy(1 / this.view.s);
            } else if (e.shiftKey && e.code === 'Digit1') {
                e.preventDefault();
                this.fitToStage();
            } else if (!mod && !e.shiftKey && !e.altKey && e.code === 'KeyH') {
                this.pan = true;
            } else if (!mod && !e.shiftKey && !e.altKey && (e.code === 'KeyP' || e.code === 'KeyB')) {
                this.pan = false;
            }
        },

        keyUp(e) {
            if (e.code !== 'Space') return;
            this.space = false;
            if (!e.target.closest('input, textarea, select, [contenteditable]')) e.preventDefault();
        },

        undo() {
            if (!this.ctx || this.history.length <= 1) return;
            this.history.pop();
            this.ctx.putImageData(this.history[this.history.length - 1], 0, 0);
        },

        clear() {
            if (!this.ctx) return;
            this.ctx.putImageData(this.history[0], 0, 0);
            this.history = [this.history[0]];
        },

        rotate() {
            if (!this.canvas) return;
            const w = this.canvas.width, h = this.canvas.height;
            const tmp = document.createElement('canvas');
            tmp.width = w;
            tmp.height = h;
            tmp.getContext('2d').putImageData(this.snapshot(), 0, 0);
            this.canvas.width = h;
            this.canvas.height = w;
            this.ctx.save();
            this.ctx.translate(h / 2, w / 2);
            this.ctx.rotate(Math.PI / 2);
            this.ctx.drawImage(tmp, -w / 2, -h / 2);
            this.ctx.restore();
            this.history = [this.snapshot()];
            this.rotated = true;
            this.fitToStage();
        },

        // Есть штрихи (или поворот), которые ещё не сохранены
        dirty() {
            return !!this.canvas && (this.history.length > 1 || this.rotated);
        },

        // Родитель просит закрыть окно или открыть другое фото
        leave(detail) {
            const target = { photo: detail && detail.photo !== undefined ? detail.photo : null };
            if (this.dirty()) {
                this.pending = target;
                return;
            }
            this.go(target);
        },

        discard() {
            const target = this.pending || { photo: null };
            this.pending = null;
            this.history = this.history.slice(0, 1);
            this.rotated = false;
            this.go(target);
        },

        go(target) {
            target.photo === null ? this.$wire.$parent.closeAnnotator() : this.$wire.$parent.annotate(target.photo);
        },

        save() {
            if (!this.canvas) return;
            this.pending = null;
            this.$wire.saveAnnotatedImage(this.canvas.toDataURL('image/png'));
        },
    }));
</script>
@endscript
