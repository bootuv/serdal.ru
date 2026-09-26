<div>
    @if ($visible)
        <div class="mb-2 flex flex-col gap-2 rounded-lg bg-promo p-4">
            <div class="flex items-start justify-between gap-2">
                <span class="text-t2 font-semibold">Приглашайте коллег</span>
                <button type="button" wire:click="hide" class="-mr-1 -mt-1 flex size-6 items-center justify-center rounded-sm text-muted hover:text-ink" aria-label="Скрыть"><x-ui.icon name="x" size="s" /></button>
            </div>
            <span class="text-t3 text-muted">+{{ $bonus }} {{ \App\Services\SubscriptionService::lessonsWord($bonus) }} за каждого учителя, который оплатит тариф</span>
            <a href="{{ $href }}" class="link self-start text-t3">Пригласить коллегу</a>
        </div>
    @endif
</div>
