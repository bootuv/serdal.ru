{{-- Звёзды рейтинга (#FFA41C, пустые #E3EFEE). value — оценка. model — свойство Livewire для ввода (кнопки 44); без model — только показ (16).
     onTint — для голубой карточки (x-ui.card rate): пустые звёзды news-2 — тон фона темнее (мятные на ней теряются), звёзды 40 в кнопках 52. --}}
@props(['value' => 0, 'model' => null, 'onTint' => false])
@php $empty = $onTint ? 'fill-news-2' : 'fill-mint-2';
    $btn = $onTint ? 'size-13' : 'size-11';
    $star = $onTint ? 'size-10' : 'size-8'; @endphp
@php $path = 'M12 2.5l2.95 6.1 6.7.9-4.9 4.7 1.2 6.7L12 17.7l-5.95 3.2 1.2-6.7-4.9-4.7 6.7-.9L12 2.5z'; @endphp
@if ($model)
    {{-- Наведение подсвечивает звёзды до курсора (как будущая оценка); ушли — снова выбранная оценка из $wire --}}
    <div {{ $attributes->class('flex gap-1') }} role="radiogroup" aria-label="Оценка"
         x-data="{ hover: 0, on(i) { return (this.hover || $wire.{{ $model }}) >= i } }" x-on:mouseleave="hover = 0">
        @for ($i = 1; $i <= 5; $i++)
            <button type="button" role="radio" aria-checked="{{ (int) $value === $i ? 'true' : 'false' }}" aria-label="{{ $i }} из 5"
                    wire:click="$set('{{ $model }}', {{ $i }})" x-on:mouseenter="hover = {{ $i }}" class="flex {{ $btn }} items-center justify-center rounded">
                <svg viewBox="0 0 24 24" class="{{ $star }} transition-colors {{ $i <= $value ? 'fill-star' : $empty }}"
                     x-bind:class="{ 'fill-star': on({{ $i }}), '{{ $empty }}': ! on({{ $i }}) }" aria-hidden="true"><path d="{{ $path }}"/></svg>
            </button>
        @endfor
    </div>
@else
    <span {{ $attributes->class('inline-flex gap-1') }} aria-label="Оценка {{ $value }} из 5">
        @for ($i = 1; $i <= 5; $i++)
            <svg viewBox="0 0 24 24" class="size-4 {{ $i <= $value ? 'fill-star' : $empty }}" aria-hidden="true"><path d="{{ $path }}"/></svg>
        @endfor
    </span>
@endif
