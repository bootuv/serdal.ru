<div>
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900"
            x-data="canvasAnnotator(@js($imageUrl))" x-init="init()" @keydown.escape.window="$wire.closeModal()"
            @keydown.space.window.prevent="spaceHeld = true" @keyup.space.window="spaceHeld = false; isPanDragging = false">
            {{-- Toolbar --}}
            <div
                class="absolute top-4 left-1/2 -translate-x-1/2 flex items-center gap-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-2 z-20">
                {{-- Color picker --}}
                <input type="color" x-model="color" class="w-8 h-8 rounded cursor-pointer border-0" title="Цвет">

                {{-- Brush sizes --}}
                <div class="flex items-center gap-1 border-l border-gray-200 dark:border-gray-700 pl-2">
                    <button @click="lineWidth = 3" :class="{'ring-2 ring-primary-500': lineWidth === 3}"
                        class="w-8 h-8 rounded flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700"
                        type="button">
                        <span class="rounded-full" :style="`width: 3px; height: 3px; background-color: ${color}`"></span>
                    </button>
                    <button @click="lineWidth = 6" :class="{'ring-2 ring-primary-500': lineWidth === 6}"
                        class="w-8 h-8 rounded flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700"
                        type="button">
                        <span class="rounded-full" :style="`width: 6px; height: 6px; background-color: ${color}`"></span>
                    </button>
                    <button @click="lineWidth = 10" :class="{'ring-2 ring-primary-500': lineWidth === 10}"
                        class="w-8 h-8 rounded flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700"
                        type="button">
                        <span class="rounded-full" :style="`width: 10px; height: 10px; background-color: ${color}`"></span>
                    </button>
                    <button @click="lineWidth = 16" :class="{'ring-2 ring-primary-500': lineWidth === 16}"
                        class="w-8 h-8 rounded flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700"
                        type="button">
                        <span class="rounded-full" :style="`width: 16px; height: 16px; background-color: ${color}`"></span>
                    </button>
                </div>

                {{-- Zoom controls --}}
                <div class="flex items-center gap-1 border-l border-gray-200 dark:border-gray-700 pl-2">
                    <button @click="zoomOut()" type="button"
                        class="w-8 h-8 rounded flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700"
                        title="Уменьшить">
                        <x-heroicon-o-minus class="w-4 h-4" />
                    </button>
                    <span class="text-xs w-12 text-center" x-text="Math.round(zoom * 100) + '%'"></span>
                    <button @click="zoomIn()" type="button"
                        class="w-8 h-8 rounded flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700"
                        title="Увеличить">
                        <x-heroicon-o-plus class="w-4 h-4" />
                    </button>
                    <button @click="resetZoom()" type="button"
                        class="w-8 h-8 rounded flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700"
                        title="Сбросить">
                        <x-heroicon-o-arrows-pointing-out class="w-4 h-4" />
                    </button>
                </div>

                {{-- Undo --}}
                <button @click="undo()" type="button"
                    class="w-8 h-8 rounded flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700 border-l border-gray-200 dark:border-gray-700 ml-1 pl-2"
                    title="Отменить">
                    <x-heroicon-o-arrow-uturn-left class="w-5 h-5" />
                </button>

                {{-- Clear --}}
                <button @click="clear()" type="button"
                    class="w-8 h-8 rounded flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700"
                    title="Очистить">
                    <x-heroicon-o-trash class="w-5 h-5" />
                </button>
            </div>

            {{-- Canvas container with zoom/pan --}}
            <div class="w-full h-full flex items-center justify-center overflow-hidden" @wheel.prevent="handleWheel($event)"
                @mousedown.middle.prevent="startPan($event)" @mousemove="handleMouseMove($event)"
                @mouseup="handleMouseUp($event)" @mouseleave="handleMouseUp($event)">
                <div class="relative transition-transform duration-100"
                    :style="`transform: translate(${panX}px, ${panY}px) scale(${zoom}); transform-origin: center center;`">
                <canvas x-ref="canvas" @mousedown="handleCanvasMouseDown($event)" @touchstart.prevent="startDrawing($event)"
                        @touchmove.prevent="draw($event)" @touchend="stopDrawing()"
                        :class="spaceHeld ? (isPanDragging ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-crosshair'"></canvas>
                </div>
            </div>

            {{-- Bottom buttons --}}
            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-3 z-20">
                <button @click="$wire.closeModal()" type="button"
                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium">
                    Отмена
                </button>
                <button @click="save()" type="button"
                    class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium">
                    Сохранить
                </button>
            </div>

            <div class="absolute bottom-4 right-4 text-gray-400 text-xs z-20">
                Колесо мыши — зум • Пробел + ЛКМ — перемещение
            </div>
        </div>
    @endif
</div>

@script
<script>
    Alpine.data('canvasAnnotator', (imageUrl) => ({
        canvas: null,
        ctx: null,
        isDrawing: false,
        color: '#ff0000',
        lineWidth: 6,
        lastX: 0,
        lastY: 0,
        history: [],
        baseImage: null,

        // Zoom and pan
        zoom: 1,
        panX: 0,
        panY: 0,
        spaceHeld: false,
        isPanDragging: false,

        // Original image dimensions
        originalWidth: 0,
        originalHeight: 0,

        init() {
            this.$nextTick(() => this.setupCanvas(imageUrl));
        },

        setupCanvas(url) {
            const canvas = this.$refs.canvas;
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            const img = new Image();
            img.crossOrigin = 'anonymous';

            img.onload = () => {
                // Use full image size for quality
                this.originalWidth = img.width;
                this.originalHeight = img.height;

                canvas.width = img.width;
                canvas.height = img.height;

                // Draw image
                ctx.drawImage(img, 0, 0);

                // Calculate initial zoom to fit screen
                const maxW = window.innerWidth * 0.9;
                const maxH = window.innerHeight * 0.85;
                this.zoom = Math.min(maxW / img.width, maxH / img.height, 1);

                // Save base state
                this.baseImage = ctx.getImageData(0, 0, canvas.width, canvas.height);
                this.saveState();

                this.canvas = canvas;
                this.ctx = ctx;
            };

            img.src = url;
        },

        getPos(e) {
            if (!this.canvas) return { x: 0, y: 0 };
            const rect = this.canvas.getBoundingClientRect();
            const touch = e.touches ? e.touches[0] : e;
            // Account for zoom
            return {
                x: (touch.clientX - rect.left) / this.zoom,
                y: (touch.clientY - rect.top) / this.zoom
            };
        },

        startDrawing(e) {
            if (!this.canvas || this.isPanning) return;
            this.isDrawing = true;
            const pos = this.getPos(e);
            this.lastX = pos.x;
            this.lastY = pos.y;
        },

        handleCanvasMouseDown(e) {
            if (this.spaceHeld) {
                // Start panning
                this.isPanDragging = true;
            } else {
                // Start drawing
                this.startDrawing(e);
            }
        },

        draw(e) {
            if (!this.isDrawing || !this.ctx || this.spaceHeld) return;

            const pos = this.getPos(e);

            this.ctx.beginPath();
            this.ctx.strokeStyle = this.color;
            // Adjust line width for zoom
            this.ctx.lineWidth = this.lineWidth / this.zoom;
            this.ctx.lineCap = 'round';
            this.ctx.lineJoin = 'round';
            this.ctx.moveTo(this.lastX, this.lastY);
            this.ctx.lineTo(pos.x, pos.y);
            this.ctx.stroke();

            this.lastX = pos.x;
            this.lastY = pos.y;
        },

        handleMouseMove(e) {
            if (this.isPanDragging) {
                this.panX += e.movementX;
                this.panY += e.movementY;
            } else if (this.isDrawing) {
                this.draw(e);
            }
        },

        handleMouseUp(e) {
            this.isPanDragging = false;
            this.stopDrawing();
        },

        stopDrawing() {
            if (this.isDrawing) {
                this.isDrawing = false;
                this.saveState();
            }
        },

        saveState() {
            if (!this.ctx || !this.canvas) return;
            this.history.push(this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height));
            if (this.history.length > 20) this.history.shift();
        },

        undo() {
            if (!this.ctx || this.history.length <= 1) return;
            this.history.pop();
            const prev = this.history[this.history.length - 1];
            this.ctx.putImageData(prev, 0, 0);
        },

        clear() {
            if (!this.ctx || !this.baseImage) return;
            this.ctx.putImageData(this.baseImage, 0, 0);
            this.history = [this.baseImage];
        },

        // Zoom controls
        handleWheel(e) {
            const delta = e.deltaY > 0 ? -0.1 : 0.1;
            this.zoom = Math.max(0.1, Math.min(5, this.zoom + delta));
        },

        zoomIn() {
            this.zoom = Math.min(5, this.zoom + 0.25);
        },

        zoomOut() {
            this.zoom = Math.max(0.1, this.zoom - 0.25);
        },

        resetZoom() {
            if (!this.canvas) return;
            const maxW = window.innerWidth * 0.9;
            const maxH = window.innerHeight * 0.85;
            this.zoom = Math.min(maxW / this.canvas.width, maxH / this.canvas.height, 1);
            this.panX = 0;
            this.panY = 0;
        },

        save() {
            if (!this.canvas) return;
            const dataUrl = this.canvas.toDataURL('image/png', 0.9);
            this.$wire.saveAnnotatedImage(dataUrl);
        }
    }));
</script>
@endscript