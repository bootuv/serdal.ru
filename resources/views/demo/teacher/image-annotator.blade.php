{{-- Демо: холст пометок вместо <livewire:image-annotator> на «Проверке работы» (App\Demo\Teacher\Review::components).
     Настоящий холст (livewire/cabinet/image-annotator) держит скрипт в @script — без Livewire он не запускается.
     Здесь — та же панель инструментов и простой карандаш поверх фото; «Сохранить пометки» показывает тост и закрывает окно.
     Классы — только из настоящих шаблонов кабинета (resources/views/demo нет в content Tailwind). --}}
@php
    $tool = 'flex size-9 items-center justify-center rounded-sm text-muted hover:text-ink';
@endphp
<div class="flex min-h-0 min-w-0 flex-1 flex-col gap-4" x-data="demoAnnotator(@js($imageUrl), @js($many))"
     x-on:annotator-save.window="save()" x-on:annotator-leave.window="leave($event.detail)">
    <div class="flex shrink-0 flex-wrap items-center gap-2" role="toolbar" aria-label="Инструменты">
        <div class="flex gap-1 rounded bg-soft p-1" role="group" aria-label="Цвет">
            <button type="button" class="{{ $tool }}" x-bind:class="color === colors.red ? 'bg-white shadow-seg' : ''" x-on:click="color = colors.red" aria-label="Красный"><span class="size-4 rounded-full bg-pen-red"></span></button>
            <button type="button" class="{{ $tool }}" x-bind:class="color === colors.blue ? 'bg-white shadow-seg' : ''" x-on:click="color = colors.blue" aria-label="Синий"><span class="size-4 rounded-full bg-pen-blue"></span></button>
            <button type="button" class="{{ $tool }}" x-bind:class="color === colors.green ? 'bg-white shadow-seg' : ''" x-on:click="color = colors.green" aria-label="Зелёный"><span class="size-4 rounded-full bg-pen-green"></span></button>
        </div>
        <div class="flex gap-1 rounded bg-soft p-1" role="group" aria-label="Действия с фото">
            <button type="button" class="{{ $tool }}" x-on:click="undo()" aria-label="Отменить последнюю пометку"><x-ui.icon name="undo" /></button>
            <button type="button" class="{{ $tool }}" x-on:click="clear()" aria-label="Стереть все пометки"><x-ui.icon name="trash" /></button>
        </div>
    </div>

    <div x-ref="stage" class="relative min-h-40 flex-1 touch-none select-none overflow-hidden rounded-lg bg-soft">
        <canvas x-ref="canvas" class="absolute left-0 top-0 block max-w-none origin-top-left shadow-card"
                x-on:pointerdown="down($event)" x-on:pointermove="move($event)" x-on:pointerup="up()" x-on:pointercancel="up()"></canvas>
    </div>

<script>
    // Цвета пера — как в настоящем холсте (токены pen-*)
    window.demoAnnotator = window.demoAnnotator || function (url, many) {
        return {
            colors: { red: '#DC2626', blue: '#2A78D6', green: '#1BAF7A' },
            color: '#DC2626', img: null, scale: 1, strokes: [], drawing: null,
            init() {
                this.color = this.colors.red;
                this.img = new Image();
                this.img.onload = () => this.fit();
                this.img.src = url;
                window.addEventListener('resize', () => this.fit());
            },
            fit() {
                const stage = this.$refs.stage, c = this.$refs.canvas;
                if (! stage.clientWidth || ! this.img.width) return;
                this.scale = Math.min(stage.clientWidth / this.img.width, stage.clientHeight / this.img.height);
                c.width = this.img.width; c.height = this.img.height;
                c.style.transform = 'translate(' + (stage.clientWidth - this.img.width * this.scale) / 2 + 'px,' + (stage.clientHeight - this.img.height * this.scale) / 2 + 'px) scale(' + this.scale + ')';
                this.draw();
            },
            point(e) { const r = this.$refs.canvas.getBoundingClientRect(); return [(e.clientX - r.left) / this.scale, (e.clientY - r.top) / this.scale]; },
            down(e) { this.$refs.canvas.setPointerCapture(e.pointerId); this.drawing = { color: this.color, pts: [this.point(e)] }; this.strokes.push(this.drawing); },
            move(e) { if (! this.drawing) return; this.drawing.pts.push(this.point(e)); this.draw(); },
            up() { this.drawing = null; },
            undo() { this.strokes.pop(); this.draw(); },
            clear() { this.strokes = []; this.draw(); },
            draw() {
                const ctx = this.$refs.canvas.getContext('2d');
                ctx.drawImage(this.img, 0, 0);
                ctx.lineWidth = 4; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
                this.strokes.forEach(s => {
                    ctx.strokeStyle = s.color; ctx.beginPath();
                    s.pts.forEach((p, i) => i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1]));
                    ctx.stroke();
                });
            },
            save() { window.demoCabinet.run({ set: many ? { photo: null, photoList: '1' } : { photo: null }, toast: 'Пометки сохранены' }, []); },
            leave(detail) { window.demoCabinet.run(detail && detail.list ? { set: { photo: null, photoList: '1' } } : { close: true }, []); },
        };
    };

    // $wire.$dispatch('annotator-leave') у крестика, «Все фото» и Esc окна: demo-cabinet.js его не передаёт — шлём событие сами
    if (! window.demoDispatchShim) {
        window.demoDispatchShim = true;
        const send = (expr) => {
            const m = String(expr || '').match(/^\s*\$dispatch\(\s*'([\w-]+)'\s*(?:,\s*([\s\S]*))?\)\s*$/);
            if (! m) return;
            let detail = null;
            try { detail = m[2] ? Function('"use strict"; return (' + m[2] + ');')() : null; } catch (e) {}
            window.dispatchEvent(new CustomEvent(m[1], { detail }));
        };
        document.addEventListener('click', (e) => {
            const el = e.target.closest && e.target.closest('[wire\\:click]');
            if (el) send(el.getAttribute('wire:click'));
        }, true);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.querySelector('[role=dialog]')) send("$dispatch('annotator-leave')");
        });
    }
</script>
</div>
