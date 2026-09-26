{{-- Окна решения по проведённому занятию: «Удалить занятие?» и «Отклонить запрос?» (трейт DecidesDeletions, макет AdminSessions). --}}
@if ($decision && $decision['mode'] === 'delete')
    <x-ui.modal title="Удалить занятие?" :sub="$decision['sub']" width="s" close="closeDecision">
        <p class="text-t1">Занятие пропадёт из истории и статистики {{ $decision['who'] }} и не будет учитываться в лимите тарифа. <x-ui.em>Вернуть нельзя.</x-ui.em></p>
        <p class="text-t2 text-muted">{{ $decision['teacher'] }} получит уведомление{{ $decision['requested'] ? ', что запрос одобрен' : '' }}.</p>
        <x-slot:footer>
            <x-ui.btn wire:click="closeDecision">Отмена</x-ui.btn>
            <x-ui.btn variant="dark" wire:click="confirmDeleteSession" wire:loading.attr="disabled" wire:target="confirmDeleteSession">Удалить занятие</x-ui.btn>
        </x-slot:footer>
    </x-ui.modal>
@elseif ($decision && $decision['mode'] === 'reject')
    <x-ui.modal title="Отклонить запрос?" :sub="$decision['sub']" width="s" close="closeDecision">
        <p class="text-t1">Занятие останется в истории, статистике и лимите тарифа. {{ $decision['teacher'] }} получит уведомление.</p>
        <x-ui.field label="Ответ учителю" name="rejectReply" :rows="3" optional wire:model="rejectReply" maxlength="1000"
                    placeholder="Например: занятие длилось больше 5 минут, поэтому оно учитывается" :hint="$decision['teacher'] . ' увидит его в уведомлении'" />
        <x-slot:footer>
            <x-ui.btn wire:click="closeDecision">Отмена</x-ui.btn>
            <x-ui.btn variant="primary" wire:click="confirmRejectSession" wire:loading.attr="disabled" wire:target="confirmRejectSession">Отклонить запрос</x-ui.btn>
        </x-slot:footer>
    </x-ui.modal>
@endif
