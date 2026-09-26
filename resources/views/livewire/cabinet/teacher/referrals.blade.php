@php
    use App\Support\HumanDate;
    $lessons = fn (int $n) => plural_ru($n, 'занятие', 'занятия', 'занятий');
    $facts = $stats['invited'] > 0
        ? 'Пригласили ' . $stats['invited'] . ' · оплатили ' . $stats['paid'] . ' · получено +' . $lessons($stats['lessons'])
        : null;
@endphp
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Пригласить коллегу" :sub="$facts" />

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
            {{-- Фокус: ссылка-приглашение --}}
            <x-ui.card focus aria-labelledby="rf-link">
                <div class="flex flex-col gap-1">
                    <h2 id="rf-link" class="text-h2 font-medium">Пригласите коллегу — получите +{{ $lessons($referrerBonus) }}</h2>
                    <p class="text-t2 text-muted">Когда учитель, которого вы пригласили, оплатит любой тариф, вам начислится +{{ $lessons($referrerBonus) }}@if ($referredBonus > 0), а ему — +{{ $lessons($referredBonus) }} в подарок@endif.</p>
                </div>
                <x-ui.copy-field label="Ваша ссылка" :value="$inviteUrl" variant="primary" button="Скопировать ссылку" />
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.btn size="s" :href="$telegramUrl" target="_blank" rel="noopener">Telegram</x-ui.btn>
                    <x-ui.btn size="s" :href="$whatsappUrl" target="_blank" rel="noopener">WhatsApp</x-ui.btn>
                </div>
                <p class="text-t3 text-muted">Приглашение засчитается, если коллега зарегистрируется по вашей ссылке</p>
            </x-ui.card>

            {{-- Приглашённые --}}
            <x-ui.card aria-labelledby="rf-list">
                <x-ui.card-head id="rf-list" title="Приглашённые" />
                @if ($invited->isEmpty())
                    <p class="text-t2 text-muted">Пока никого — отправьте ссылку коллеге, здесь появятся все, кто пришёл по ней.</p>
                @else
                    <x-ui.list>
                        @foreach ($invited as $row)
                            @php
                                $date = $row['date'] ? HumanDate::date($row['date']) : null;
                                $sub = match ($row['state']) {
                                    'application' => 'Заявка на рассмотрении' . ($date ? ' · подана ' . $date : ''),
                                    'waiting' => 'Зарегистрировался' . ($date ? ' ' . $date : '') . ' · ждём оплату',
                                    'credited' => 'Оплатил тариф',
                                    'limit' => 'Оплатил тариф · лимит бонусов в этом месяце',
                                    'rejected' => 'Бонус не начислен',
                                    'revoked' => 'Платёж возвращён, бонус списан',
                                    default => $row['status'],
                                };
                            @endphp
                            <x-ui.row :chevron="false" wire:key="rf-{{ $row['state'] }}-{{ $row['id'] }}">
                                <x-ui.avatar :name="$row['name']" :id="$row['id']" />
                                <x-ui.text :title="$row['name']" :sub="$sub" />
                                @if ($row['state'] === 'credited' && $row['lessons'] > 0)
                                    <x-ui.badge tone="ok">+{{ $lessons($row['lessons']) }}</x-ui.badge>
                                @endif
                            </x-ui.row>
                        @endforeach
                    </x-ui.list>
                @endif
            </x-ui.card>
        </div>

        {{-- Условия --}}
        <x-ui.card aria-labelledby="rf-terms">
            <x-ui.card-head id="rf-terms" title="Условия" />
            <x-ui.list>
                <x-ui.row><x-ui.text :title="'+' . $lessons($referrerBonus) . ' вам'" sub="За каждого коллегу, который оплатит любой тариф" /></x-ui.row>
                @if ($referredBonus > 0)
                    <x-ui.row><x-ui.text :title="'+' . $lessons($referredBonus) . ' коллеге'" sub="В подарок к его первой оплате" /></x-ui.row>
                @endif
                <x-ui.row><x-ui.text title="Бонусы не сгорают" sub="Расходуются после лимита тарифа" /></x-ui.row>
                <x-ui.row><x-ui.text title="Один раз за коллегу" sub="За первую оплату тарифа. Если платёж вернут, бонус спишется" /></x-ui.row>
                @if ($monthlyLimit > 0)
                    <x-ui.row><x-ui.text :title="'До ' . plural_ru($monthlyLimit, 'коллеги', 'коллег', 'коллег') . ' в месяц'" sub="Сверх лимита бонус в этом месяце не начисляется" /></x-ui.row>
                @endif
                <x-ui.row><x-ui.text :title="'Ссылка помнится ' . plural_ru($cookieDays, 'день', 'дня', 'дней')" sub="Коллега может подать заявку не сразу — приглашение засчитается" /></x-ui.row>
            </x-ui.list>
        </x-ui.card>
    </div>
</div>
