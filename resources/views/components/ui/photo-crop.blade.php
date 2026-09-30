{{-- Загрузка фото профиля с обрезкой: кнопка + окно, где фото двигают, увеличивают и поворачивают (resources/js/photo-crop.js).
     На сервер уходит уже готовый квадрат — в свойство Livewire model (по умолчанию photo), дальше всё как у обычной загрузки.
     Подпись кнопки — слот; size и icon — как у x-ui.btn. Сам компонент места не занимает (contents): кнопка встаёт в ряд родителя.
     Окно повторяет x-ui.modal (480), но открывается без запроса к серверу. --}}
@props(['model' => 'photo', 'size' => 'm', 'icon' => null])
@php
    $tool = 'flex size-9 shrink-0 items-center justify-center rounded-sm text-muted hover:text-ink disabled:opacity-50';
@endphp
<div class="contents" x-data="photoCrop(@js($model))">
    <x-ui.btn :size="$size" :icon="$icon" x-on:click="$refs.file.click()" wire:loading.attr="disabled" wire:target="{{ $model }}" {{ $attributes }}>{{ $slot }}</x-ui.btn>
    <input type="file" x-ref="file" x-on:change="pick($event)" accept="image/*" class="sr-only" tabindex="-1" aria-label="Фото профиля">

    <div wire:ignore x-show="open" x-cloak class="fixed inset-0 z-20 flex items-center justify-center bg-scrim p-4 lg:p-8"
         x-on:keydown.escape.window="if (open) close()"
         x-on:mousedown="pressed = $event.target === $el" x-on:click="if (pressed && $event.target === $el) close(); pressed = false">
        <div role="dialog" aria-modal="true" aria-label="Фото профиля" class="flex max-h-full w-full max-w-modal-s flex-col overflow-hidden rounded-xl bg-white shadow-modal">
            <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                    <h2 class="text-h2 font-medium">Фото профиля</h2>
                    <p class="text-t2 text-muted">Подвиньте фото и выберите масштаб</p>
                </div>
                <x-ui.btn square icon="x" x-on:click="close()" aria-label="Закрыть" />
            </div>

            <div class="flex flex-col gap-4 overflow-y-auto p-6">
                <div class="flex justify-center">
                    <canvas x-ref="stage" tabindex="0" class="aspect-square w-full max-w-dialogs cursor-move touch-none select-none rounded-lg bg-soft"
                            aria-label="Фото в рамке. Двигается мышью, пальцем или стрелками"
                            x-on:pointerdown="down($event)" x-on:pointermove="move($event)" x-on:pointerup="up($event)" x-on:pointercancel="up($event)"
                            x-on:wheel.prevent="wheel($event)" x-on:gesturestart.prevent="gestureStart()" x-on:gesturechange.prevent="gestureChange($event)"
                            x-on:keydown="key($event)"></canvas>
                </div>
                <div class="flex items-center gap-2" role="toolbar" aria-label="Инструменты">
                    <div class="flex min-w-0 flex-1 items-center gap-1 rounded bg-soft p-1" role="group" aria-label="Масштаб">
                        <button type="button" class="{{ $tool }}" x-on:click="zoomBy(1 / 1.25)" x-bind:disabled="zoom <= 1" aria-label="Уменьшить"><x-ui.icon name="zoom-out" /></button>
                        <input type="range" class="range" min="1" step="0.01" x-bind:max="maxZoom" x-bind:value="zoom" x-bind:disabled="maxZoom <= 1"
                               x-on:input="setZoom($event.target.value)" aria-label="Масштаб">
                        <button type="button" class="{{ $tool }}" x-on:click="zoomBy(1.25)" x-bind:disabled="zoom >= maxZoom" aria-label="Увеличить"><x-ui.icon name="zoom-in" /></button>
                    </div>
                    <div class="flex rounded bg-soft p-1">
                        <button type="button" class="{{ $tool }}" x-on:click="rotate()" aria-label="Повернуть фото"><x-ui.icon name="rotate" /></button>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-line px-6 py-4 sm:flex-row sm:items-center sm:gap-4">
                <p x-show="failed" x-cloak class="min-w-0 text-t2 font-medium text-danger-fg" role="alert">Не удалось загрузить фото. Попробуйте ещё раз</p>
                <div class="flex flex-wrap items-center gap-2 sm:ml-auto sm:shrink-0 sm:flex-nowrap">
                    <x-ui.btn x-on:click="close()">Отмена</x-ui.btn>
                    <x-ui.btn variant="primary" x-on:click="apply()" x-bind:disabled="busy">
                        <span x-show="! busy">Готово</span><span x-show="busy" x-cloak>Загружаем…</span>
                    </x-ui.btn>
                </div>
            </div>
        </div>
    </div>
</div>
