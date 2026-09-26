{{-- «Войти в класс» на странице занятия ученика: открыт, закрыт до оплаты или ещё рано (подпись вместо кнопки). --}}
@if ($archived)
@elseif ($blocked)
    <x-ui.btn :size="$size" icon="lock" disabled>Войти в класс</x-ui.btn>
@elseif ($canJoin)
    <x-ui.btn variant="primary" :size="$size" icon="video" :href="$joinUrl" target="_blank" rel="noopener">Войти в класс</x-ui.btn>
@elseif ($joinHint)
    <span class="text-t2 text-muted">{{ $joinHint }}</span>
@endif
