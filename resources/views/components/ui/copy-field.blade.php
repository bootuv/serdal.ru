{{-- Поле со ссылкой только для чтения и кнопка «Скопировать» (ссылка-приглашение). hint — строка под полем; hideLabel — подпись только для экранного диктора;
     variant/button — стиль и подпись кнопки (primary — если копирование и есть главное действие экрана). --}}
@props(['label', 'value', 'message' => 'Ссылка скопирована', 'hint' => null, 'variant' => 'outline', 'button' => 'Скопировать', 'hideLabel' => false, 'id' => 'copy-' . substr(md5($value), 0, 8)])
<div class="flex flex-col gap-2">
    <label for="{{ $id }}" @class(['text-t2 font-medium', 'sr-only' => $hideLabel])>{{ $label }}</label>
    {{-- В узкой колонке (карточка сбоку) кнопка уходит под поле, а не вылезает за карточку --}}
    <div class="flex flex-wrap items-center gap-2">
        <input id="{{ $id }}" type="text" readonly value="{{ $value }}" class="field min-w-40 flex-1 text-muted" x-data x-on:focus="$el.select()">
        <x-ui.copy :value="$value" :message="$message" :variant="$variant" icon="share">{{ $button }}</x-ui.copy>
    </div>
    @if ($hint)<span class="text-t3 text-muted">{{ $hint }}</span>@endif
</div>
