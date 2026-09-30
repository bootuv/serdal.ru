<x-mail::message>
<x-slot:preheader>К сожалению, сейчас мы не можем одобрить заявку</x-slot:preheader>
# Заявка отклонена

К сожалению, сейчас мы не можем одобрить вашу заявку на кабинет учителя в {{ \App\Support\Seo::SITE_NAME }}.

@if (! empty($reason))
<x-mail::panel>
**Причина:** {{ $reason }}
</x-mail::panel>
@endif

@php $support = \App\Support\OfferSettings::legal()['legal_email'] ?? null; @endphp
@if ($support)
Если остались вопросы, напишите нам: [{{ $support }}](mailto:{{ $support }}).
@endif
</x-mail::message>
