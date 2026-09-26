{{-- Выбор человека (кому выдать задание и т.п.): аватар + имя, высота 44. Выбранный — мятный с тёмной обводкой и галочкой. --}}
@props(['user', 'on' => false])
<button type="button" aria-pressed="{{ $on ? 'true' : 'false' }}" {{ $attributes->class([
    'inline-flex h-11 max-w-full items-center gap-2 rounded pl-1 pr-3 text-t1-s font-medium',
    'bg-mint shadow-selected' => $on,
    'bg-white shadow-outline hover:shadow-outline-ink' => ! $on,
]) }}>
    <x-ui.avatar :user="$user"/>
    <span class="truncate">{{ $user->name }}</span>
    @if ($on)<x-ui.icon name="check"/>@endif
</button>
