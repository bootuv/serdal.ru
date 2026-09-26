{{-- Плеер записей занятий: своё управление поверх <video> (resources/js/video-player.js).
     Жёлтая кнопка «Смотреть» до начала, панель внизу: пауза, время, громкость, скорость 0,5–2× (запоминается), полный экран.
     Панель прячется во время просмотра, если не двигать мышью. Клавиши: пробел, ←/→ — 10 с, ↑/↓ — громкость, M, F.
     Меню скорости может выходить за верх плеера (на телефоне плеер ниже меню), поэтому корпус не обрезает содержимое.
     src — адрес видео, title — название (для экранного диктора). --}}
@props(['src', 'title' => null])
<div x-data="videoPlayer" tabindex="0" role="region" aria-label="{{ $title ? 'Запись: ' . $title : 'Запись занятия' }}"
     x-on:keydown="key($event)" x-on:mousemove="wake()" x-on:touchstart.passive="wake()" x-on:mouseleave="if (playing && ! speedOpen) idle = true"
     x-bind:class="idle ? 'cursor-none' : ''"
     {{ $attributes->class('group relative w-full rounded-lg bg-ink text-white') }}>
    <video x-ref="video" src="{{ $src }}" preload="metadata" playsinline class="block aspect-video w-full rounded-lg"
           x-on:click="toggle()" x-on:dblclick="toggleFull()">Ваш браузер не поддерживает воспроизведение видео.</video>

    {{-- Жёлтая кнопка до первого запуска --}}
    <button type="button" x-show="! started" x-on:click="toggle()" aria-label="Смотреть запись"
            class="absolute left-1/2 top-1/2 flex size-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-brand text-ink shadow-modal hover:bg-brand-hover">
        <x-ui.icon name="play" />
    </button>

    {{-- Загрузка --}}
    <span x-show="waiting && playing" x-cloak class="pointer-events-none absolute left-1/2 top-1/2 size-8 -translate-x-1/2 -translate-y-1/2 animate-spin rounded-full border-2 border-white border-t-transparent" aria-hidden="true"></span>

    {{-- Панель управления --}}
    <div x-show="started && (! idle || ! playing)" x-cloak x-transition.opacity
         class="absolute inset-x-0 bottom-0 flex flex-col gap-1 rounded-b-lg bg-scrim px-3 pb-2 pt-1 lg:px-4">
        <div class="vp-track">
            <progress class="vp-buffer" max="1" x-bind:value="duration ? buffered / duration : 0" aria-hidden="true"></progress>
            <progress class="vp-played" max="1" x-bind:value="duration ? current / duration : 0" aria-hidden="true"></progress>
            <input type="range" class="vp-seek" min="0" step="0.1" x-bind:max="duration || 0" x-bind:value="current"
                   x-on:input="seek($event.target.value)" aria-label="Перемотка" x-bind:aria-valuetext="time(current) + ' из ' + time(duration)">
        </div>

        <div class="flex items-center gap-1">
            <button type="button" x-on:click="toggle()" class="flex size-9 shrink-0 items-center justify-center rounded hover:bg-white/10"
                    x-bind:aria-label="playing ? 'Пауза' : 'Смотреть'">
                <span x-show="! playing"><x-ui.icon name="play" /></span>
                <span x-show="playing" x-cloak><x-ui.icon name="pause" /></span>
            </button>
            <span class="px-1 text-t2 tabular-nums" x-text="time(current) + ' / ' + time(duration)"></span>

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
