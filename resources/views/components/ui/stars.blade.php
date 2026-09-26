{{-- Звёзды рейтинга (#FFA41C, пустые #E3EFEE). value — оценка. model — свойство Livewire для ввода (кнопки 44); без model — только показ (16). --}}
@props(['value' => 0, 'model' => null])
@php $path = 'M12 2.5l2.95 6.1 6.7.9-4.9 4.7 1.2 6.7L12 17.7l-5.95 3.2 1.2-6.7-4.9-4.7 6.7-.9L12 2.5z'; @endphp
@if ($model)
    <div {{ $attributes->class('flex gap-1') }} role="radiogroup" aria-label="Оценка">
        @for ($i = 1; $i <= 5; $i++)
            <button type="button" role="radio" aria-checked="{{ (int) $value === $i ? 'true' : 'false' }}" aria-label="{{ $i }} из 5"
                    wire:click="$set('{{ $model }}', {{ $i }})" class="flex size-11 items-center justify-center rounded hover:bg-soft">
                <svg viewBox="0 0 24 24" class="size-8 {{ $i <= $value ? 'fill-star' : 'fill-mint-2' }}" aria-hidden="true"><path d="{{ $path }}"/></svg>
            </button>
        @endfor
    </div>
@else
    <span {{ $attributes->class('inline-flex gap-1') }} aria-label="Оценка {{ $value }} из 5">
        @for ($i = 1; $i <= 5; $i++)
            <svg viewBox="0 0 24 24" class="size-4 {{ $i <= $value ? 'fill-star' : 'fill-mint-2' }}" aria-hidden="true"><path d="{{ $path }}"/></svg>
        @endfor
    </span>
@endif
