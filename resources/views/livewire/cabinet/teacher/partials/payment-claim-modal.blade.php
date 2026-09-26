{{-- Окно «Ученик сообщил об оплате»: занятия, сумма, комментарий, чеки; «Подтвердить оплату» / «Отклонить».
     Состояние — трейт ReviewsPaymentClaims (claimId, claimRejecting, claimReason), данные — $claimView. --}}
@if (! empty($claimView))
    @if ($claimRejecting)
        <x-ui.modal title="Не подтверждать оплату?" :sub="$claimView['student']" close="closeClaim" width="s">
            <p class="text-t1">Занятия останутся неоплаченными, {{ $claimView['firstName'] }} получит уведомление и сможет сообщить об оплате ещё раз.</p>
            <x-ui.field label="Причина" name="claimReason" :rows="3" optional wire:model="claimReason" placeholder="Например: перевод пока не пришёл" />
            <x-slot:footer>
                <x-ui.btn wire:click="$set('claimRejecting', false)">Назад</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="rejectClaim" wire:loading.attr="disabled" wire:target="rejectClaim">Отклонить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @else
        <x-ui.modal title="Ученик сообщил об оплате" :sub="$claimView['student'] . ' · ' . $claimView['when']" close="closeClaim">
            <div class="flex flex-col gap-2">
                <span class="text-t2 font-medium">За какие занятия</span>
                <x-ui.list>
                    @foreach ($claimView['rows'] as $row)
                        <x-ui.row wire:key="claim-row-{{ $row['id'] }}">
                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="truncate text-t1-s font-medium">{{ $row['title'] }}</span>
                                @if ($row['done'])<span class="text-t2 text-muted">Уже отмечено</span>@endif
                            </div>
                            @if ($row['amount'])<span class="shrink-0 text-t1-s font-medium">{{ $row['amount'] }}</span>@endif
                        </x-ui.row>
                    @endforeach
                </x-ui.list>
            </div>
            @if ($claimView['sum'])
                <div class="flex items-center justify-between gap-4 border-t border-line pt-4">
                    <span class="text-t1 font-medium">Итого</span>
                    <span class="text-h2 font-medium">{{ $claimView['sum'] }}</span>
                </div>
            @endif

            @if ($claimView['comment'])
                <div class="flex flex-col gap-1">
                    <span class="text-t2 font-medium">Комментарий</span>
                    <p class="whitespace-pre-line text-t1">{{ $claimView['comment'] }}</p>
                </div>
            @endif

            <div class="flex flex-col gap-2">
                <span class="text-t2 font-medium">Чек</span>
                @if (empty($claimView['files']))
                    <p class="text-t2 text-muted">Чек не приложен — сверьте оплату сами.</p>
                @else
                    <div class="flex flex-col gap-3">
                        @foreach ($claimView['files'] as $file)
                            @if ($file['image'] && $file['url'])
                                <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="block" wire:key="claim-file-{{ $loop->index }}" data-lightbox data-name="{{ $file['name'] }}">
                                    <img src="{{ $file['url'] }}" alt="Чек: {{ $file['name'] }}" class="w-full rounded-lg shadow-line">
                                </a>
                            @else
                                <div class="flex items-center gap-3" wire:key="claim-file-{{ $loop->index }}">
                                    <x-ui.file-tile :name="$file['name']" />
                                    <span class="min-w-0 flex-1 truncate text-t1-s font-medium">{{ $file['name'] }}</span>
                                    @if ($file['url'])<a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="link shrink-0 text-t2">Открыть</a>@endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            <x-slot:note>Подтверждение отметит занятия оплаченными</x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="$set('claimRejecting', true)">Отклонить</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="confirmClaim" wire:loading.attr="disabled" wire:target="confirmClaim">Подтвердить оплату</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
@endif
