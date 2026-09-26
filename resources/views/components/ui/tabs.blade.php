{{-- Вкладки: подчёркивание 2px ink, без заливки. items: ['key' => 'Подпись'], model — свойство Livewire.
     counts: ['key' => n] — красный счётчик (только то, что требует действия). --}}
@props(['items', 'model', 'active', 'counts' => []])
<div {{ $attributes->class('flex gap-6 border-b border-line') }} role="tablist">
    @foreach ($items as $key => $label)
        <button type="button" role="tab" wire:click="$set('{{ $model }}', '{{ $key }}')" aria-selected="{{ $active === $key ? 'true' : 'false' }}"
                @class(['-mb-px inline-flex h-11 items-center gap-2 border-b-2 text-t1-s',
                        'border-ink font-semibold text-ink' => $active === $key,
                        'border-transparent font-medium text-muted hover:text-ink' => $active !== $key])>
            {{ $label }}<x-ui.count :value="$counts[$key] ?? 0" />
        </button>
    @endforeach
</div>
