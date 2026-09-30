@props(['value', 'label' => null, 'note' => null, 'code' => false])
{{ $label ? $label . ': ' : '' }}{{ $value }}{{ $note ? ' (' . $note . ')' : '' }}
