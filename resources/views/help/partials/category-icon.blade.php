{{-- Иконка категории: ключ линейной иконки (App\Support\HelpIcons) или эмодзи из старых данных. $icon — значение поля. --}}
@if (\App\Support\HelpIcons::isKey($icon ?? null))
    <span class="help-cat-ic"><x-ui.icon :name="$icon" /></span>
@elseif (filled($icon ?? null))
    {{ $icon }}&nbsp;
@endif
