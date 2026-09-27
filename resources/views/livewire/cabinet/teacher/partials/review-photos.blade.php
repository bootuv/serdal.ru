{{-- Фото ответа ученика плиткой: на экране проверки и в окне пометок (список фото). Нажатие открывает фото для пометок. --}}
<div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
    @foreach ($photos as $i => $p)
        <button type="button" wire:click="annotate({{ $i }})" wire:key="{{ $key }}-{{ $p['path'] }}" aria-label="Открыть {{ $p['label'] }} для пометок"
                class="flex min-w-0 flex-col gap-3 rounded-lg p-2 pb-3 text-left shadow-outline hover:shadow-outline-ink">
            <span class="relative block">
                <img src="{{ $p['url'] }}" alt="" loading="lazy" class="h-40 w-full rounded-sm bg-soft object-cover">
                @if ($p['annotated'])
                    <span class="absolute right-2 top-2 flex size-8 items-center justify-center rounded-sm bg-white text-ink shadow-seg"><x-ui.icon name="pencil" size="s" /></span>
                @endif
            </span>
            <span class="flex items-center gap-2 px-1">
                <span class="flex min-w-0 flex-1 flex-col gap-1">
                    <span class="truncate text-t2 font-medium">{{ $p['name'] }}</span>
                    @if ($p['annotated'])
                        <span class="truncate text-t2 font-semibold text-ink">Есть пометки</span>
                    @else
                        <span class="truncate text-t2 text-muted">Без пометок</span>
                    @endif
                </span>
                <x-ui.icon name="chevron-right" class="text-faint" />
            </span>
        </button>
    @endforeach
</div>
