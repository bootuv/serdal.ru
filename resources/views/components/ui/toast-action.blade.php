{{-- Тост с действием: «Отменить» (обратимое действие) или ссылка («Открыть в пользователях»). Вид — как x-ui.toast.
     Показывается, пока родитель рендерит его (@if), и сам закрывается через 8 с.
     message — текст; close — метод Livewire, который убирает тост; action + actionLabel — кнопка-ссылка; href + linkLabel — ссылка. --}}
@props(['message', 'close', 'action' => null, 'actionLabel' => 'Отменить', 'href' => null, 'linkLabel' => null])
<div x-data x-init="setTimeout(() => $wire.{{ $close }}(), 8000)" {{ $attributes->class('fixed inset-x-0 bottom-tabbar z-30 flex justify-center px-4 lg:bottom-8') }} role="status" aria-live="polite">
    <div class="flex max-w-modal-s items-center gap-3 rounded-lg bg-white py-3 pl-4 pr-2 text-t1-s shadow-modal">
        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-ok-bg text-ok-fg"><x-ui.icon name="check" size="s" /></span>
        <span>{{ $message }}</span>
        @if ($action)
            <button type="button" wire:click="{{ $action }}" class="link shrink-0 text-t2">{{ $actionLabel }}</button>
        @endif
        @if ($href)
            <a href="{{ $href }}" class="link shrink-0 text-t2">{{ $linkLabel }}</a>
        @endif
        <button type="button" wire:click="{{ $close }}" class="flex size-9 shrink-0 items-center justify-center rounded text-muted hover:text-ink" aria-label="Закрыть"><x-ui.icon name="x" size="s" /></button>
    </div>
</div>
