<x-mail::message>
<x-slot:preheader>{{ $lead }}</x-slot:preheader>
# {{ $title }}

{{ $name }}, {{ $lead }}

<x-mail::highlight :label="'Ваш взнос за ' . $month" :value="$amount" :note="$note" />

@if (count($lines) > 1)
<x-mail::rows title="Из чего складывается взнос" :rows="$lines" />
@endif
@if ($expenses)
<x-mail::rows title="Расходы в месяц" :rows="$expenses" />
@endif

<x-mail::button :url="$url">
Открыть расходы
</x-mail::button>

<p class="muted">Когда переведёте, отметьте взнос в разделе «Основатели» — напоминания прекратятся.</p>
</x-mail::message>
