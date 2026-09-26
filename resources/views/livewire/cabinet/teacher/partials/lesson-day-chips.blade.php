{{-- Дни недели для повтора: кнопки 44 (пн…вс). selected — выбранные дни (0 — вс), action — метод Livewire, slot — индекс доп. времени. --}}
@php
    $weekDays = [1 => ['пн', 'понедельник'], 2 => ['вт', 'вторник'], 3 => ['ср', 'среда'], 4 => ['чт', 'четверг'], 5 => ['пт', 'пятница'], 6 => ['сб', 'суббота'], 0 => ['вс', 'воскресенье']];
    $selected = array_map('intval', $selected ?? []);
@endphp
<div class="flex flex-wrap gap-1" role="group" aria-label="{{ $label ?? 'Дни недели' }}">
    @foreach ($weekDays as $day => [$short, $full])
        @php $on = in_array($day, $selected, true); @endphp
        <button type="button" wire:click="{{ $action }}({{ $day }}{{ isset($slot) ? ', ' . $slot : '' }})" aria-pressed="{{ $on ? 'true' : 'false' }}" aria-label="{{ $full }}"
                @class(['size-11 rounded text-t2 font-medium transition-colors',
                        'bg-ink text-white' => $on,
                        'text-ink shadow-outline hover:shadow-outline-ink' => ! $on])>{{ $short }}</button>
    @endforeach
</div>
