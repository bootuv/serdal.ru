{{-- Тост: слушает событие Livewire 'toast' (message). Размещён один раз в раскладке. Вызов: $this->dispatch('toast', message: 'Готово'). --}}
<div x-data="{ show: false, message: '', t: null }"
     x-on:toast.window="message = $event.detail.message; show = true; clearTimeout(t); t = setTimeout(() => show = false, 4000)"
     x-show="show" x-cloak x-transition.opacity
     class="fixed inset-x-0 bottom-tabbar z-30 flex justify-center px-4 lg:bottom-8" role="status" aria-live="polite">
    <div class="flex items-center gap-3 rounded-lg bg-white py-3 pl-4 pr-2 text-t1-s shadow-modal">
        <span class="flex size-6 items-center justify-center rounded-full bg-ok-bg text-ok-fg"><x-ui.icon name="check" size="s" /></span>
        <span x-text="message"></span>
        <button type="button" class="flex size-9 items-center justify-center rounded text-muted hover:text-ink" x-on:click="show = false" aria-label="Закрыть"><x-ui.icon name="x" size="s" /></button>
    </div>
</div>
