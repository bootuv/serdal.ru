{{-- Сегменты (фильтры, виды): подложка soft 12, активный — белый 8. items: ['key' => 'Подпись']. --}}
@props(['items', 'model', 'active'])
<div {{ $attributes->class('flex gap-1 rounded bg-soft p-1') }} role="group">
    @foreach ($items as $key => $label)
        <button type="button" wire:click="$set('{{ $model }}', '{{ $key }}')" aria-pressed="{{ (string) $active === (string) $key ? 'true' : 'false' }}"
                @class(['h-9 flex-1 truncate rounded-sm px-3 text-t2',
                        'bg-white font-semibold text-ink shadow-seg' => (string) $active === (string) $key,
                        'font-medium text-muted hover:text-ink' => (string) $active !== (string) $key])>{{ $label }}</button>
    @endforeach
</div>
