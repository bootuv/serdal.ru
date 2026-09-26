{{-- Бейдж статуса: danger — проблема, ok — результат, neutral — остальное. Норму не показываем. --}}
@props(['tone' => 'neutral'])
@php $tones = ['danger' => 'bg-danger-bg text-danger-fg', 'ok' => 'bg-ok-bg text-ok-fg', 'neutral' => 'bg-soft text-muted']; @endphp
<span {{ $attributes->class('inline-flex h-6 shrink-0 items-center whitespace-nowrap rounded-full px-2 text-t3 font-medium ' . $tones[$tone]) }}>{{ $slot }}</span>
