{{-- Шапка карточки: h2 слева (count — красный счётчик того, что ждёт действия), действие (ссылка или кнопка S) справа. Не помещаются в строку (телефон) — действие переносится под заголовок. --}}
@props(['title', 'id' => null, 'count' => 0])
<div {{ $attributes->class('flex min-h-9 flex-wrap items-center justify-between gap-x-4 gap-y-1') }}>
    <div class="flex min-w-0 items-center gap-2">
        <h2 {!! $id ? 'id="' . e($id) . '"' : '' !!} class="text-h2 font-medium">{{ $title }}</h2>
        <x-ui.count :value="$count" />
    </div>
    @isset($action)<div class="flex shrink-0 items-center gap-2">{{ $action }}</div>@endisset
</div>
