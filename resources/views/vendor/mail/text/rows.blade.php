@props(['rows', 'title' => null])
@if ($title)
{{ $title }}:
@endif
@foreach ($rows as $row)
- {{ $row['label'] }}: {{ $row['value'] }}{{ ! empty($row['sub']) ? ' (' . $row['sub'] . ')' : '' }}
@endforeach
