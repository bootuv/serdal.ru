<div>
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900"
            x-data="canvasAnnotator(@js($imageUrl))" x-init="init()" @keydown.escape.window="$wire.closeModal()"
            @keydown.space.window.prevent="spaceHeld = true" @keyup.space.window="spaceHeld = false; isPanDragging = false">
            {{-- Toolbar --}}
            <div
                class="absolute top-4 left-1/2 -translate-x-1/2 flex flex-wrap items-center gap-1 sm:gap-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-1.5 sm:p-2 z-20 max-w-[95vw]">
                {{-- Color picker --}}
                <label class="relative w-6 h-6 cursor-pointer" title="Цвет">
                    <span class="block w-6 h-6 rounded-full border border-gray-300 dark:border-gray-600 overflow-hidden bg-gradient-to-br from-red-500 via-green-500 to-blue-500"></span>
                    <input type="color" x-model="color" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                </label>

                {{-- Brush sizes --}}
                <div class="flex items-center gap-0.5 border-l border-gray-200 dark:border-gray-700 pl-1 sm:pl-2">
                    <template x-for="size in [3, 6, 10, 16]" :key="size">
                        <button @click="lineWidth = size" type="button"
                            class="w-6 h-6 rounded flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                            :class="lineWidth === size ? 'bg-gray-100 dark:bg-gray-700' : ''">
                            <span class="rounded-full" :style="`width: ${Math.max(size, 3)}px; height: ${Math.max(size, 3)}px; background-color: ${color}`"></span>
                        </button>
                    </template>
                </div>

                {{-- Pan mode toggle + Rotate --}}
                <div class="flex items-center gap-1 border-l border-gray-200 dark:border-gray-700 pl-1 sm:pl-2">
                    <button @click="panMode = !panMode" type="button"
                        :class="panMode ? 'bg-primary-500 text-white' : 'text-gray-700 dark:text-gray-300'"
                        class="w-8 h-8 rounded flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        title="Перемещение">
                        <x-heroicon-o-hand-raised class="w-4 h-4" />
                    </button>
                    <button @click="rotateImage(90)" type="button"
                        class="w-8 h-8 rounded flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700"
                        title="Повернуть">
                        <x-heroicon-o-arrow-path class="w-4 h-4" />
                    </button>
                </div>

                {{-- Zoom controls --}}
                <div class="flex items-center gap-1 border-l border-gray-200 dark:border-gray-700 pl-1 sm:pl-2">
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
                    <x-heroicon-o-arrow-uturn-left class="w-4 h-4" />
                </button>

                {{-- Clear --}}
                <button @click="clear()" type="button"
                    class="w-8 h-8 rounded flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700"
                    title="Очистить">
                    <x-heroicon-o-trash class="w-4 h-4" />
                </button>
            </div>

            {{-- Canvas container with zoom/pan --}}
            <div class="w-full h-full flex items-center justify-center overflow-hidden" @wheel.prevent="handleWheel($event)"
                @mousedown.middle.prevent="startPan($event)" @mousemove="handleMouseMove($event)"
                @mouseup="handleMouseUp($event)" @mouseleave="handleMouseUp($event)" @touchstart="handleTouchStart($event)"
                @touchmove.prevent="handleTouchMove($event)" @touchend="handleTouchEnd($event)"
                @touchcancel="handleTouchEnd($event)">
                <div class="relative transition-transform duration-100"
                    :style="`transform: translate(${panX}px, ${panY}px) scale(${zoom}); transform-origin: center center;`">
                    <canvas x-ref="canvas" @mousedown="handleCanvasMouseDown($event)"
                        :class="(panMode || spaceHeld) ? (isPanDragging ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-crosshair'"></canvas>
                </div>
            </div>

            {{-- Bottom buttons --}}
            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-3 z-20">
                <x-filament::button color="gray" @click="$wire.closeModal()">
                    Отмена
                </x-filament::button>
                <x-filament::button color="primary" @click="save" wire:target="saveAnnotatedImage">
                    Сохранить
                </x-filament::button>
            </div>

            <div class="absolute bottom-4 right-4 text-gray-400 text-xs z-20 hidden sm:block">
                Колесо мыши — зум • ✋ или Пробел+ЛКМ — перемещение • Два пальца — зум/перемещение
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
        panMode: false,

        // Touch state
        touchStartDist: 0,
        touchStartZoom: 1,
        touchStartMidX: 0,
        touchStartMidY: 0,
        touchStartPanX: 0,
        touchStartPanY: 0,
        isTouchPanning: false,

        // Single-finger pan (touch)
        singleTouchStartX: 0,
        singleTouchStartY: 0,
        singleTouchPanStartX: 0,
        singleTouchPanStartY: 0,

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
            // Account for zoom (rect already reflects CSS transform scale)
            return {
                x: (touch.clientX - rect.left) * (this.canvas.width / rect.width),
                y: (touch.clientY - rect.top) * (this.canvas.height / rect.height)
            };
        },

        startDrawing(e) {
            if (!this.canvas || this.isTouchPanning) return;
            this.isDrawing = true;
            const pos = this.getPos(e);
            this.lastX = pos.x;
            this.lastY = pos.y;
        },

        handleCanvasMouseDown(e) {
            if (this.spaceHeld || this.panMode) {
                // Start panning
                this.isPanDragging = true;
            } else {
                // Start drawing
                this.startDrawing(e);
            }
        },

        draw(e) {
            if (!this.isDrawing || !this.ctx || this.spaceHeld || this.panMode) return;

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

        // === Touch handlers ===

        getTouchDist(t1, t2) {
            const dx = t1.clientX - t2.clientX;
            const dy = t1.clientY - t2.clientY;
            return Math.sqrt(dx * dx + dy * dy);
        },

        getTouchMid(t1, t2) {
            return {
                x: (t1.clientX + t2.clientX) / 2,
                y: (t1.clientY + t2.clientY) / 2
            };
        },

        handleTouchStart(e) {
            if (e.touches.length === 2) {
                // Two-finger: start pinch-zoom + pan
                e.preventDefault();
                this.isDrawing = false;
                this.isTouchPanning = true;
                this.touchStartDist = this.getTouchDist(e.touches[0], e.touches[1]);
                this.touchStartZoom = this.zoom;
                const mid = this.getTouchMid(e.touches[0], e.touches[1]);
                this.touchStartMidX = mid.x;
                this.touchStartMidY = mid.y;
                this.touchStartPanX = this.panX;
                this.touchStartPanY = this.panY;
            } else if (e.touches.length === 1) {
                if (this.panMode) {
                    // Single finger pan mode
                    e.preventDefault();
                    this.isTouchPanning = true;
                    this.singleTouchStartX = e.touches[0].clientX;
                    this.singleTouchStartY = e.touches[0].clientY;
                    this.singleTouchPanStartX = this.panX;
                    this.singleTouchPanStartY = this.panY;
                } else {
                    // Drawing mode — let canvas handle via startDrawing
                    e.preventDefault();
                    this.startDrawing(e);
                }
            }
        },

        handleTouchMove(e) {
            if (e.touches.length === 2 && this.isTouchPanning) {
                // Pinch zoom
                const dist = this.getTouchDist(e.touches[0], e.touches[1]);
                const scale = dist / this.touchStartDist;
                this.zoom = Math.max(0.1, Math.min(5, this.touchStartZoom * scale));

                // Two-finger pan
                const mid = this.getTouchMid(e.touches[0], e.touches[1]);
                this.panX = this.touchStartPanX + (mid.x - this.touchStartMidX);
                this.panY = this.touchStartPanY + (mid.y - this.touchStartMidY);
            } else if (e.touches.length === 1) {
                if (this.panMode && this.isTouchPanning) {
                    // Single-finger pan
                    const dx = e.touches[0].clientX - this.singleTouchStartX;
                    const dy = e.touches[0].clientY - this.singleTouchStartY;
                    this.panX = this.singleTouchPanStartX + dx;
                    this.panY = this.singleTouchPanStartY + dy;
                } else if (this.isDrawing) {
                    // Drawing
                    this.draw(e);
                }
            }
        },

        handleTouchEnd(e) {
            if (e.touches.length < 2) {
                this.isTouchPanning = false;
            }
            if (e.touches.length === 0) {
                this.isTouchPanning = false;
                this.stopDrawing();
            }
        },

        // === State management ===

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

        // === Zoom controls ===

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

        // === Rotation ===

        rotateImage(degrees) {
            if (!this.canvas || !this.ctx) return;

            const srcCanvas = this.canvas;
            const srcCtx = this.ctx;
            const srcW = srcCanvas.width;
            const srcH = srcCanvas.height;

            // Capture current content
            const currentData = srcCtx.getImageData(0, 0, srcW, srcH);

            // Create temp canvas with current content
            const tmpCanvas = document.createElement('canvas');
            tmpCanvas.width = srcW;
            tmpCanvas.height = srcH;
            const tmpCtx = tmpCanvas.getContext('2d');
            tmpCtx.putImageData(currentData, 0, 0);

            // Determine new dimensions
            const rad = (degrees * Math.PI) / 180;
            const absSin = Math.abs(Math.sin(rad));
            const absCos = Math.abs(Math.cos(rad));
            const newW = Math.round(srcW * absCos + srcH * absSin);
            const newH = Math.round(srcW * absSin + srcH * absCos);

            // Resize main canvas
            srcCanvas.width = newW;
            srcCanvas.height = newH;

            // Draw rotated
            srcCtx.save();
            srcCtx.translate(newW / 2, newH / 2);
            srcCtx.rotate(rad);
            srcCtx.drawImage(tmpCanvas, -srcW / 2, -srcH / 2);
            srcCtx.restore();

            // Update base image
            this.baseImage = srcCtx.getImageData(0, 0, newW, newH);
            this.history = [this.baseImage];

            // Recalculate zoom to fit
            const maxW = window.innerWidth * 0.9;
            const maxH = window.innerHeight * 0.85;
            this.zoom = Math.min(maxW / newW, maxH / newH, 1);
            this.panX = 0;
            this.panY = 0;
        },

        // === Save ===

        save() {
            if (!this.canvas) return;
            const dataUrl = this.canvas.toDataURL('image/png', 0.9);
            this.$wire.saveAnnotatedImage(dataUrl);
        }
    }));
</script>
@endscript