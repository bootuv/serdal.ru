{{-- Плеер записей занятий: своё управление поверх <video> (resources/js/video-player.js).
     Тёмная рамка вокруг видео: записи — чаще белая доска, плеер не должен сливаться со страницей.
     Панель — внизу поверх видео, видна при наведении (касании), на паузе и пока открыто меню скорости.
     Жёлтая кнопка «Смотреть» до начала; панель: пауза, перемотка, время, громкость, скорость 0,5–2× (запоминается), полный экран.
     Клавиши: пробел, ←/→ — 10 с, ↑/↓ — громкость, M, F. src — адрес видео, title — название (для экранного диктора). --}}
@props(['src', 'title' => null])
<div x-data="videoPlayer" tabindex="0" role="region" aria-label="{{ $title ? 'Запись: ' . $title : 'Запись занятия' }}"
     x-on:keydown="key($event)" x-on:mousemove="wake()" x-on:touchstart.passive="wake()" x-on:mouseleave="rest()" x-on:focusin="wake()"
     x-bind:class="(full ? 'justify-center ' : '') + (playing && ! hover ? 'cursor-none' : '')"
     {{ $attributes->class('group relative flex w-full flex-col rounded-lg bg-ink p-1 text-white shadow-card') }}>
    <div class="relative min-h-0" x-bind:class="full ? 'flex flex-1 items-center justify-center' : ''">
        <video x-ref="video" src="{{ $src }}" preload="metadata" playsinline
               class="block w-full rounded bg-ink" x-bind:class="full ? 'h-full object-contain' : 'aspect-video'"
               x-on:click="toggle()" x-on:dblclick="toggleFull()">Ваш браузер не поддерживает воспроизведение видео.</video>

        {{-- Жёлтая кнопка до первого запуска --}}
        <button type="button" x-show="! started" x-on:click="toggle()" aria-label="Смотреть запись"
                class="absolute left-1/2 top-1/2 flex size-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-brand text-ink shadow-modal hover:bg-brand-hover">
            <x-ui.icon name="play" />
        </button>

        {{-- Загрузка: жёлтое кольцо на тёмном кружке — видно и на белой доске, и на тёмном видео --}}
        <span x-show="waiting && playing" x-cloak class="pointer-events-none absolute left-1/2 top-1/2 flex size-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-scrim" aria-hidden="true">
            <span class="size-6 animate-spin rounded-full border-2 border-brand border-t-transparent"></span>
        </span>

    {{-- Панель управления — внизу поверх видео --}}
    <div x-show="started && (hover || ! playing || speedOpen || scrubbing)" x-cloak x-transition.opacity
         class="absolute inset-x-0 bottom-0 flex flex-col gap-1 rounded-b bg-scrim px-3 pb-2 pt-1 lg:px-4">
        <div class="vp-track">
            <progress class="vp-buffer" max="1" x-bind:value="duration ? buffered / duration : 0" aria-hidden="true"></progress>
            <progress class="vp-played" max="1" x-bind:value="duration ? shown() / duration : 0" aria-hidden="true"></progress>
            <input type="range" class="vp-seek" min="0" step="0.1" x-bind:max="duration || 0" x-bind:value="shown()"
                   x-on:pointerdown="scrubStart()" x-on:input="scrub($event.target.value)" x-on:change="scrubEnd($event.target.value)"
                   aria-label="Перемотка" x-bind:aria-valuetext="time(shown()) + ' из ' + time(duration)">
        </div>

        <div class="flex items-center gap-1">
            <button type="button" x-on:click="toggle()" class="flex size-9 shrink-0 items-center justify-center rounded hover:bg-white/10"
                    x-bind:aria-label="playing ? 'Пауза' : 'Смотреть'">
                <span x-show="! playing"><x-ui.icon name="play" /></span>
                <span x-show="playing" x-cloak><x-ui.icon name="pause" /></span>
            </button>
            <span class="px-1 text-t2 tabular-nums" x-text="time(shown()) + ' / ' + time(duration)"></span>

            <span class="flex-1"></span>

            <button type="button" x-on:click="toggleMute()" class="flex size-9 shrink-0 items-center justify-center rounded hover:bg-white/10"
                    x-bind:aria-label="muted ? 'Включить звук' : 'Выключить звук'">
                <span x-show="! muted"><x-ui.icon name="volume" /></span>
                <span x-show="muted" x-cloak><x-ui.icon name="volume-off" /></span>
            </button>
            <input type="range" class="vp-volume hidden sm:block" min="0" max="1" step="0.05" x-bind:value="muted ? 0 : volume"
                   x-on:input="setVolume($event.target.value)" aria-label="Громкость">

            {{-- Скорость --}}
            <div class="relative" x-on:click.outside="speedOpen = false" x-on:keydown.escape.stop="speedOpen = false">
                <button type="button" x-on:click="speedOpen = ! speedOpen" x-bind:aria-expanded="speedOpen" aria-haspopup="menu"
                        class="flex h-9 min-w-11 items-center justify-center rounded px-2 text-t2 font-semibold tabular-nums hover:bg-white/10"
                        aria-label="Скорость воспроизведения" x-text="speedLabel(speed)"></button>
                <div x-show="speedOpen" x-cloak role="menu" aria-label="Скорость"
                     class="absolute bottom-full right-0 z-10 mb-2 flex flex-col rounded-lg bg-white p-1 text-ink shadow-modal">
                    <template x-for="rate in speeds" :key="rate">
                        <button type="button" role="menuitemradio" x-bind:aria-checked="rate === speed" x-on:click="setSpeed(rate)"
                                class="flex h-9 items-center gap-2 whitespace-nowrap rounded-sm px-3 text-t2 hover:bg-soft"
                                x-bind:class="rate === speed ? 'font-semibold' : 'font-medium'">
                            <span class="flex size-4 items-center justify-center"><span x-show="rate === speed"><x-ui.icon name="check" size="s" /></span></span>
                            <span x-text="rate === 1 ? 'Обычная' : speedLabel(rate)"></span>
                        </button>
                    </template>
                </div>
            </div>

            <button type="button" x-on:click="toggleFull()" class="flex size-9 shrink-0 items-center justify-center rounded hover:bg-white/10"
                    x-bind:aria-label="full ? 'Выйти из полноэкранного режима' : 'На весь экран'">
                <span x-show="! full"><x-ui.icon name="expand" /></span>
                <span x-show="full" x-cloak><x-ui.icon name="shrink" /></span>
            </button>
        </div>
    </div>
    </div>
</div>
