{{-- Тур по кабинету: затемнение с «окном» вокруг подсвеченного элемента и карточка шага.
     Логика и место карточки — resources/js/tour.js; координаты подсветки и карточки ставит скрипт.
     Карточка проявляется, когда встала на место (ready); перелёт с элемента на элемент — только в пределах экрана (animate). --}}
<div>
@if ($steps)
    <div x-data="cabinetTour(@js($steps), $wire.auto, { desktop: 'Тур можно пройти снова: «Помощь» → «Тур по кабинету»', phone: 'Тур можно пройти снова: «Ещё» → «Тур по кабинету»' })" x-on:keydown.window="key($event)"
         x-on:resize.window="place()" x-on:scroll.window.passive="place()">
        <div x-show="open" x-cloak class="fixed inset-0 z-40">
            {{-- Без подсвеченного элемента — сплошное затемнение, с ним — тень вокруг элемента; клики мимо карточки не проходят на страницу --}}
            <div x-show="! spot" class="absolute inset-0 bg-scrim"></div>
            <div x-ref="spot" x-show="spot" class="pointer-events-none fixed rounded shadow-spot"
                 x-bind:class="animate && 'transition-all duration-300 ease-out'"></div>

            <div x-ref="box" class="pointer-events-none"
                 x-bind:class="{
                    'fixed inset-0 flex items-center justify-center p-4': mode === 'center',
                    'fixed inset-x-0 bottom-tabbar flex justify-center px-4': mode === 'dock',
                    'fixed w-tour': mode === 'float',
                    'transition-all duration-300 ease-out': animate && mode === 'float',
                 }">
                <div role="dialog" aria-modal="true" aria-labelledby="tour-title" aria-describedby="tour-text"
                     class="pointer-events-auto flex w-full max-w-modal-s flex-col gap-4 rounded-xl bg-white p-6 shadow-modal transition-opacity duration-200"
                     x-bind:class="ready ? 'opacity-100' : 'opacity-0'">
                    <div class="flex items-start justify-between gap-4">
                        <h2 id="tour-title" class="text-h2 font-medium" x-text="step.title"></h2>
                        <x-ui.btn square size="s" icon="x" x-on:click="close()" aria-label="Закрыть тур" />
                    </div>
                    <p id="tour-text" class="text-t1" x-text="step.text"></p>
                    <a x-show="step.help && ! isFirst && ! isLast" x-bind:href="step.help" target="_blank" rel="noopener" class="link self-start text-t2">Подробнее в базе знаний</a>
                    <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
                        <span x-show="counter" class="mr-auto text-t2 text-muted" x-text="counter"></span>
                        <x-ui.btn x-show="isFirst" x-on:click="close()">Позже</x-ui.btn>
                        <x-ui.btn x-show="i > 1 && ! isLast" x-on:click="prev()">Назад</x-ui.btn>
                        <x-ui.btn x-show="isLast" x-bind:href="step.help" href="#" target="_blank" rel="noopener">Открыть базу знаний</x-ui.btn>
                        <x-ui.btn variant="primary" x-ref="primary" x-on:click="next()"><span x-show="isFirst">Начать тур</span><span x-show="isLast" x-cloak>Готово</span><span x-show="! isFirst && ! isLast" x-cloak>Далее</span></x-ui.btn>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
</div>
