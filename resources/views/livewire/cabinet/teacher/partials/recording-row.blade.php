{{-- Строка записи (экран «Записи» учителя). $r — запись, $open — id открытой, $focus — строка в мятном блоке,
     $selecting — режим «Выбрать», $picked — выбранные id. --}}
@php
    $playing = $open === $r['id'];
    $on = in_array($r['id'], $picked, true);
    $main = 'flex min-w-0 flex-1 items-center gap-4 text-left';
@endphp
<x-ui.row wire:key="rec-{{ $r['id'] }}">
    @if ($selecting)
        <button type="button" wire:click="toggle({{ $r['id'] }})" class="{{ $main }}" aria-pressed="{{ $on ? 'true' : 'false' }}">
            <span class="flex size-10 shrink-0 items-center justify-center">
                <span @class(['flex size-6 items-center justify-center rounded-sm',
                              'bg-ink text-white' => $on,
                              'bg-white shadow-outline-ink' => ! $on])>@if ($on)<x-ui.icon name="check" size="s" />@endif</span>
            </span>
    @elseif ($r['video'])
        <button type="button" wire:click="play({{ $r['id'] }})" x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
                class="{{ $main }}" aria-pressed="{{ $playing ? 'true' : 'false' }}">
    @elseif ($r['externalUrl'])
        <a href="{{ $r['externalUrl'] }}" target="_blank" rel="noopener" class="{{ $main }}">
    @else
        <div class="{{ $main }}">
    @endif
        @unless ($selecting)
            @if ($playing)
                <span class="flex size-10 shrink-0 items-center justify-center rounded bg-ink text-white"><x-ui.icon name="play" size="s" /></span>
            @elseif ($r['group'])
                <x-ui.avatar group />
            @else
                <x-ui.avatar :user="$r['person']" :name="$r['person'] ? null : $r['title']" />
            @endif
        @endunless
        <span class="flex min-w-0 flex-1 flex-col gap-1">
            <span class="truncate text-t1 font-medium">{{ $r['title'] }}</span>
            <span class="text-t2 text-muted">{{ $r['meta'] }}@if ($r['soon'])<span class="lg:hidden"> · <x-ui.em>удалится {{ $r['expires'] }}</x-ui.em></span>@endif</span>
        </span>
    @if ($selecting || $r['video'])
        </button>
    @elseif ($r['externalUrl'])
        </a>
    @else
        </div>
    @endif

    @if ($selecting)
        {{-- В режиме выбора — только отметка --}}
    @elseif ($r['soon'])
        <span class="hidden text-t2 lg:inline"><x-ui.em>Удалится {{ $r['expires'] }}</x-ui.em></span>
        @if ($r['downloadUrl'])
            <x-ui.btn size="s" icon="download" :href="$r['downloadUrl']" class="hidden lg:inline-flex">Скачать</x-ui.btn>
            <x-ui.btn size="s" square icon="download" :href="$r['downloadUrl']" class="lg:hidden" aria-label="Скачать запись" />
        @endif
    @elseif ($playing)
        <span class="text-t2"><x-ui.em>Открыта</x-ui.em></span>
    @elseif ($r['video'] || $r['externalUrl'])
        <x-ui.icon name="chevron-right" class="text-faint" />
    @else
        <x-ui.badge>Готовится</x-ui.badge>
    @endif
</x-ui.row>
