{{-- Главное число письма в мятном блоке: сумма или код. label — подпись сверху, note — строка снизу, code — моноширинно с разрядкой. --}}
@props(['value', 'label' => null, 'note' => null, 'code' => false])
<table class="panel" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="panel-content">
@if ($label)
<div class="highlight-label">{{ $label }}</div>
@endif
<div class="highlight-value {{ $code ? 'highlight-code' : '' }}">{{ $value }}</div>
@if ($note)
<div class="highlight-note">{{ $note }}</div>
@endif
</td>
</tr>
</table>
