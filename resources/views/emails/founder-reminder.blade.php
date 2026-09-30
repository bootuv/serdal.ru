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

@if ($payment)
<x-mail::rows title="Куда переводить" :rows="$payment" />
@endif

@if ($url)
<x-mail::button :url="$url">
Открыть мой сбор
</x-mail::button>

<p class="muted">Страница откроется после входа в ваш профиль {{ \App\Support\Seo::SITE_NAME }}. Когда переведёте, нажмите там «Я перевёл» — напоминания прекратятся, а взнос отметим, когда перевод придёт.</p>

<x-slot:subcopy>
Кнопка не открывается? Скопируйте ссылку в адресную строку браузера: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
@else
<p class="muted">Когда переведёте, сообщите тому, кто ведёт сбор, — он отметит взнос, и напоминания прекратятся.</p>
@endif
</x-mail::message>
