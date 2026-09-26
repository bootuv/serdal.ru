{{-- Лайтбокс: картинка на весь экран поверх страницы. Размещён один раз в раскладке кабинета.
     Открывается кликом по ссылке на картинку (resources/js/lightbox.js): по расширению в адресе или по атрибуту data-lightbox.
     Несколько картинок рядом (одно сообщение, окно, раздел) листаются стрелками и клавишами ← →.
     Закрывается крестиком, Esc и кликом мимо картинки. «Скачать» — исходный файл. --}}
<div x-data="{
        items: [], i: 0, down: false,
        get cur() { return this.items[this.i] || null },
        open(detail) { this.items = detail.items; this.i = detail.index; document.body.dataset.lightbox = '1' },
        close() { this.items = []; setTimeout(() => delete document.body.dataset.lightbox) },
        step(d) { if (this.items.length > 1) this.i = (this.i + d + this.items.length) % this.items.length },
     }"
     x-on:lightbox.window="open($event.detail)"
     x-on:keydown.escape.window="if (cur) close()"
     x-on:keydown.arrow-left.window="if (cur) step(-1)"
     x-on:keydown.arrow-right.window="if (cur) step(1)"
     x-show="cur" x-cloak x-transition.opacity
     class="fixed inset-0 z-50 flex flex-col bg-ink" role="dialog" aria-modal="true" aria-label="Просмотр изображения">
    <div class="flex items-center gap-3 px-4 py-3 lg:px-6">
        <span class="min-w-0 flex-1 truncate text-t1-s font-medium text-white" x-text="cur?.name"></span>
        <span class="shrink-0 text-t2 text-faint" x-show="items.length > 1" x-text="(i + 1) + ' из ' + items.length"></span>
        <a x-bind:href="cur?.src" x-bind:download="cur?.name || ''" target="_blank" rel="noopener"
           class="flex h-11 shrink-0 items-center gap-2 rounded bg-white px-4 text-t1-s font-medium text-ink hover:bg-soft"><x-ui.icon name="download" />Скачать</a>
        <button type="button" x-on:click="close()" class="flex size-11 shrink-0 items-center justify-center rounded bg-white text-ink hover:bg-soft" aria-label="Закрыть"><x-ui.icon name="x" /></button>
    </div>
    <div class="relative flex min-h-0 flex-1 items-center justify-center p-4 lg:px-12 lg:pb-8"
         x-on:mousedown="down = $event.target === $el" x-on:click="if (down && $event.target === $el) close(); down = false">
        <img x-bind:src="cur?.src" x-bind:alt="cur?.name || ''" class="max-h-full max-w-full rounded object-contain">
        <template x-if="items.length > 1">
            <div>
                <button type="button" x-on:click="step(-1)" class="absolute left-4 top-1/2 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white text-ink hover:bg-soft lg:left-6" aria-label="Предыдущее изображение"><x-ui.icon name="chevron-left" /></button>
                <button type="button" x-on:click="step(1)" class="absolute right-4 top-1/2 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white text-ink hover:bg-soft lg:right-6" aria-label="Следующее изображение"><x-ui.icon name="chevron-right" /></button>
            </div>
        </template>
    </div>
</div>
