{{-- Тост: слушает событие Livewire 'toast' (message, tone). Размещён один раз в раскладке. Вызов: $this->dispatch('toast', message: 'Готово').
     tone: 'ok' (по умолчанию, мятная галочка) | 'danger' (не получилось — красный крестик, держится дольше).
     После перехода на другую страницу — session()->flash('toast', 'Готово') или session()->flash('error', 'Не получилось…'). --}}
@php $flash = session('error') ? ['message' => session('error'), 'tone' => 'danger'] : (session('toast') ? ['message' => session('toast'), 'tone' => 'ok'] : null); @endphp
<div x-data="{ show: false, message: '', tone: 'ok', t: null }"
     x-init="const flash = @js($flash); if (flash) $nextTick(() => $dispatch('toast', flash))"
     x-on:toast.window="message = $event.detail.message; tone = $event.detail.tone || 'ok'; show = true; clearTimeout(t); t = setTimeout(() => show = false, tone === 'danger' ? 8000 : 4000)"
     x-show="show" x-cloak x-transition.opacity
     class="fixed inset-x-0 bottom-tabbar z-30 flex justify-center px-4 lg:bottom-8" x-bind:role="tone === 'danger' ? 'alert' : 'status'" aria-live="polite">
    <div class="flex max-w-modal-s items-center gap-3 rounded-lg bg-white py-3 pl-4 pr-2 text-t1-s shadow-modal">
        <span class="flex size-6 shrink-0 items-center justify-center rounded-full" x-bind:class="tone === 'danger' ? 'bg-danger-bg text-danger-fg' : 'bg-ok-bg text-ok-fg'">
            <x-ui.icon name="check" size="s" x-show="tone !== 'danger'" /><x-ui.icon name="x" size="s" x-show="tone === 'danger'" x-cloak />
        </span>
        <span x-text="message"></span>
        <button type="button" class="flex size-9 shrink-0 items-center justify-center rounded text-muted hover:text-ink" x-on:click="show = false" aria-label="Закрыть"><x-ui.icon name="x" size="s" /></button>
    </div>
</div>
