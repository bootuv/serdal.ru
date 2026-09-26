{{-- Полоса прогресса 8 (лимит занятий, загрузка). value/max; onMint — белая дорожка внутри мятного блока. --}}
@props(['value' => 0, 'max' => 100, 'label', 'onMint' => false])
@php $percent = $max > 0 ? max(0, min(100, round($value / $max * 100))) : 0; @endphp
<svg {{ $attributes->class('h-2 w-full') }} role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="{{ $max }}" aria-valuenow="{{ min($value, $max) }}">
    <rect width="100%" height="8" rx="4" @class(['fill-white' => $onMint, 'fill-soft' => ! $onMint]) />
    @if ($percent > 0)<rect width="{{ $percent }}%" height="8" rx="4" class="fill-ink" />@endif
</svg>
