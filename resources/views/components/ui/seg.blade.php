{{-- Сегменты (фильтры, виды): подложка soft 12, активный — белый 8. items: ['key' => 'Подпись'].
     fit — ширина по содержимому (фильтр в строке), иначе сегменты делят ширину поровну. --}}
@props(['items', 'model', 'active', 'fit' => false])
<div {{ $attributes->class(['gap-1 rounded bg-soft p-1', 'inline-flex max-w-full self-start overflow-x-auto' => $fit, 'flex' => ! $fit]) }} role="group">
    @foreach ($items as $key => $label)
        <button type="button" wire:click="$set('{{ $model }}', '{{ $key }}')" aria-pressed="{{ (string) $active === (string) $key ? 'true' : 'false' }}"
                @class(['h-9 truncate rounded-sm px-3 text-t2', 'flex-1' => ! $fit, 'shrink-0' => $fit,
                        'bg-white font-semibold text-ink shadow-seg' => (string) $active === (string) $key,
                        'font-medium text-muted hover:text-ink' => (string) $active !== (string) $key])>{{ $label }}</button>
    @endforeach
</div>
