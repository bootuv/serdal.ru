{{-- Строки «подпись — значение» с линиями. rows: [['label' => …, 'value' => …, 'sub' => …], …]; title — подпись над списком. --}}
@props(['rows', 'title' => null])
<table class="rows" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@if ($title)
<tr>
<td class="rows-title" colspan="2">{{ $title }}</td>
</tr>
@endif
@foreach ($rows as $row)
<tr>
<td class="rows-label">{{ $row['label'] }}@if (! empty($row['sub']))<span class="rows-sub">{{ $row['sub'] }}</span>@endif</td>
<td class="rows-value">{{ $row['value'] }}</td>
</tr>
@endforeach
</table>
