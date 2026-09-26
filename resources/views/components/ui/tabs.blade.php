{{-- Вкладки: подчёркивание 2px ink, без заливки. items: ['key' => 'Подпись'], model — свойство Livewire.
     counts: ['key' => n] — красный счётчик (только то, что требует действия).
     Подписи не переносятся: если вкладки не помещаются (телефон), ряд прокручивается вбок. --}}
@props(['items', 'model', 'active', 'counts' => []])
<div {{ $attributes->except('aria-label')->class('min-w-0 overflow-x-auto') }}>
    <div class="inline-flex min-w-full gap-6 border-b border-line align-top" role="tablist" {!! $attributes->has('aria-label') ? 'aria-label="' . e($attributes->get('aria-label')) . '"' : '' !!}>
        @foreach ($items as $key => $label)
            <button type="button" role="tab" wire:click="$set('{{ $model }}', '{{ $key }}')" aria-selected="{{ $active === $key ? 'true' : 'false' }}"
                    @class(['-mb-px inline-flex h-11 shrink-0 items-center gap-2 whitespace-nowrap border-b-2 text-t1-s',
                            'border-ink font-semibold text-ink' => $active === $key,
                            'border-transparent font-medium text-muted hover:text-ink' => $active !== $key])>
                {{ $label }}<x-ui.count :value="$counts[$key] ?? 0" />
            </button>
        @endforeach
    </div>
</div>
