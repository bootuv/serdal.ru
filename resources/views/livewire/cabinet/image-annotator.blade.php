{{-- Холст пометок нового кабинета (App\Livewire\ImageAnnotator, embedded). Стоит внутри x-ui.modal родителя;
     кнопка «Сохранить пометки» в подвале окна шлёт событие annotator-save. Сохранение — saveAnnotatedImage (как в старом кабинете).
     Иконок инструментов в x-ui.icon нет — пока нарисованы здесь. Цвета пера — hex в скрипте. --}}
@php
    $svg = fn (string $path) => '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true">' . $path . '</svg>';
    $tool = 'flex size-9 items-center justify-center rounded-sm text-muted hover:text-ink';
@endphp
<div class="flex min-w-0 flex-1 flex-col gap-4">
    @if ($imageUrl)
        <div class="flex min-w-0 flex-col gap-4" x-data="cabinetAnnotator(@js($imageUrl))" x-on:annotator-save.window="save()">
            <div class="flex flex-wrap items-center gap-2" role="toolbar" aria-label="Инструменты">
                <div class="flex gap-1 rounded bg-soft p-1" role="group" aria-label="Инструмент">
                    <button type="button" class="{{ $tool }}" x-bind:class="pan ? '' : 'bg-white text-ink shadow-seg'" x-bind:aria-pressed="pan ? 'false' : 'true'" x-on:click="pan = false" aria-label="Карандаш">{!! $svg('<path d="M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4"/>') !!}</button>
                    <button type="button" class="{{ $tool }}" x-bind:class="pan ? 'bg-white text-ink shadow-seg' : ''" x-bind:aria-pressed="pan ? 'true' : 'false'" x-on:click="pan = true" aria-label="Перемещение">{!! $svg('<path d="M12 3v18M3 12h18M12 3 9.5 5.5M12 3l2.5 2.5M12 21l-2.5-2.5M12 21l2.5-2.5M3 12l2.5-2.5M3 12l2.5 2.5M21 12l-2.5-2.5M21 12l-2.5 2.5"/>') !!}</button>
                </div>
                <div class="flex gap-1 rounded bg-soft p-1" role="group" aria-label="Цвет">
                    <button type="button" class="{{ $tool }}" x-bind:class="color === colors.red ? 'bg-white shadow-seg' : ''" x-bind:aria-pressed="color === colors.red ? 'true' : 'false'" x-on:click="color = colors.red; pan = false" aria-label="Красный"><span class="size-4 rounded-full bg-danger"></span></button>
                    <button type="button" class="{{ $tool }}" x-bind:class="color === colors.blue ? 'bg-white shadow-seg' : ''" x-bind:aria-pressed="color === colors.blue ? 'true' : 'false'" x-on:click="color = colors.blue; pan = false" aria-label="Синий"><span class="size-4 rounded-full bg-chart-1"></span></button>
                    <button type="button" class="{{ $tool }}" x-bind:class="color === colors.green ? 'bg-white shadow-seg' : ''" x-bind:aria-pressed="color === colors.green ? 'true' : 'false'" x-on:click="color = colors.green; pan = false" aria-label="Зелёный"><span class="size-4 rounded-full bg-chart-2"></span></button>
                </div>
                <div class="flex gap-1 rounded bg-soft p-1" role="group" aria-label="Действия с фото">
                    <button type="button" class="{{ $tool }}" x-on:click="undo()" aria-label="Отменить последнюю пометку">{!! $svg('<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>') !!}</button>
                    <button type="button" class="{{ $tool }}" x-on:click="clear()" aria-label="Стереть все пометки">{!! $svg('<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>') !!}</button>
                    <button type="button" class="{{ $tool }}" x-on:click="rotate()" aria-label="Повернуть фото">{!! $svg('<path d="M20 12a8 8 0 1 1-2.3-5.7M20 4v4h-4"/>') !!}</button>
                    <button type="button" class="{{ $tool }}" x-on:click="zoomBy(1 / 1.5)" aria-label="Уменьшить">{!! $svg('<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4M8 11h6"/>') !!}</button>
                    <button type="button" class="{{ $tool }}" x-on:click="zoomBy(1.5)" aria-label="Увеличить">{!! $svg('<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4M11 8v6M8 11h6"/>') !!}</button>
                </div>
                <span class="text-t2 text-muted" wire:loading wire:target="saveAnnotatedImage">Сохраняем…</span>
            </div>

            <div x-ref="stage" class="flex min-h-40 overflow-auto rounded-lg bg-soft" x-bind:class="pan ? 'cursor-grab' : ''">
                <canvas x-ref="canvas" class="m-auto block max-w-none touch-none" x-bind:class="pan ? '' : 'cursor-crosshair'"
                        x-on:pointerdown="down($event)" x-on:pointermove="move($event)" x-on:pointerup="up()" x-on:pointercancel="up()"></canvas>
            </div>
            <p x-show="failed" x-cloak class="text-t2 text-muted">Не удалось загрузить фото — закройте окно и попробуйте ещё раз.</p>
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
        pan: false,
        canvas: null,
        ctx: null,
        fit: 1,
        zoom: 1,
        history: [],
        drawing: false,
        last: null,
        drag: null,
        failed: false,

        init() {
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
            };
            img.onerror = () => { this.failed = true; };
            img.src = url;
        },

        snapshot() {
            return this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        },

        // Фото целиком в окне: по ширине области и не выше 60% экрана
        fitToStage() {
            const maxW = this.$refs.stage.clientWidth || this.canvas.width;
            const maxH = Math.max(240, window.innerHeight * 0.6);
            this.fit = Math.min(maxW / this.canvas.width, maxH / this.canvas.height, 1);
            this.zoom = 1;
            this.applyZoom();
        },

        applyZoom() {
            const s = this.fit * this.zoom;
            this.canvas.style.width = Math.round(this.canvas.width * s) + 'px';
            this.canvas.style.height = Math.round(this.canvas.height * s) + 'px';
        },

        zoomBy(f) {
            if (!this.canvas) return;
            this.zoom = Math.min(5, Math.max(1, this.zoom * f));
            this.applyZoom();
        },

        pos(e) {
            const r = this.canvas.getBoundingClientRect();
            return {
                x: (e.clientX - r.left) * (this.canvas.width / r.width),
                y: (e.clientY - r.top) * (this.canvas.height / r.height),
            };
        },

        down(e) {
            if (!this.canvas) return;
            if (e.target.setPointerCapture) e.target.setPointerCapture(e.pointerId);
            if (this.pan) {
                const st = this.$refs.stage;
                this.drag = { x: e.clientX, y: e.clientY, left: st.scrollLeft, top: st.scrollTop };
                return;
            }
            this.drawing = true;
            this.last = this.pos(e);
            this.stroke(this.last);
        },

        move(e) {
            if (this.drag) {
                const st = this.$refs.stage;
                st.scrollLeft = this.drag.left - (e.clientX - this.drag.x);
                st.scrollTop = this.drag.top - (e.clientY - this.drag.y);
                return;
            }
            if (!this.drawing) return;
            const p = this.pos(e);
            this.stroke(p);
            this.last = p;
        },

        // Линия 4 px на экране при любом масштабе
        stroke(p) {
            const scale = this.canvas.getBoundingClientRect().width / this.canvas.width;
            const ctx = this.ctx;
            ctx.strokeStyle = this.color;
            ctx.lineWidth = 4 / scale;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.beginPath();
            ctx.moveTo(this.last.x, this.last.y);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
        },

        up() {
            if (this.drawing) {
                this.drawing = false;
                this.history.push(this.snapshot());
                if (this.history.length > 21) this.history.splice(1, 1);
            }
            this.drag = null;
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
            this.fitToStage();
        },

        save() {
            if (!this.canvas) return;
            this.$wire.saveAnnotatedImage(this.canvas.toDataURL('image/png'));
        },
    }));
</script>
@endscript
