{{-- Плитка даты 48: число и день недели. today — обводка ink. --}}
@props(['date', 'today' => false])
@php $d = \Illuminate\Support\Carbon::parse($date); $wd = ['вс','пн','вт','ср','чт','пт','сб'][$d->dayOfWeek]; @endphp
<span {{ $attributes->class(['flex size-12 shrink-0 flex-col items-center justify-center rounded', 'bg-white shadow-outline-ink' => $today, 'bg-soft' => ! $today]) }}>
    <span class="text-t1 font-semibold leading-5">{{ $d->day }}</span>
    <span class="text-t3 {{ $today ? 'text-ink' : 'text-muted' }}">{{ $wd }}</span>
</span>
