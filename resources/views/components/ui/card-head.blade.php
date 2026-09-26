{{-- Шапка карточки: h2 слева, действие (ссылка или кнопка S) справа. --}}
@props(['title', 'id' => null])
<div {{ $attributes->class('flex min-h-9 items-center justify-between gap-4') }}>
    <h2 @if($id) id="{{ $id }}" @endif class="text-h2 font-medium">{{ $title }}</h2>
    @isset($action)<div class="flex shrink-0 items-center gap-2">{{ $action }}</div>@endisset
</div>
