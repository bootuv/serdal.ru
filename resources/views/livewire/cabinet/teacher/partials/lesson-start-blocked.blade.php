{{-- Окно «Занятие недоступно» (макет LsStartBlocked): лимит тарифа или закончилась подписка. Данные — StartsLessons::startBlock(). --}}
@if ($startBlockedOpen && $startBlock)
    <x-ui.modal :title="$startBlock['title']" close="closeStartBlocked" width="s">
        <p class="text-t1 text-ink">{{ $startBlock['text'] }}</p>
        @if ($startBlock['hint'])<p class="text-t2 text-muted">{{ $startBlock['hint'] }}</p>@endif
        <x-slot:note>
            <a href="{{ $startBlock['compareUrl'] }}" class="link">Сравнить тарифы</a>
            @if ($startBlock['referral']) · <a href="{{ $startBlock['referral']['url'] }}" class="link">{{ $startBlock['referral']['label'] }}</a>@endif
        </x-slot:note>
        <x-slot:footer>
            <x-ui.btn wire:click="closeStartBlocked">Закрыть</x-ui.btn>
            <x-ui.btn variant="primary" :href="$startBlock['primary']['url']">{{ $startBlock['primary']['label'] }}</x-ui.btn>
        </x-slot:footer>
    </x-ui.modal>
@endif
