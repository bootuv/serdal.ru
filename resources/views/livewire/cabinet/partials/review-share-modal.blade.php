{{-- Окно «Поделиться отзывом» (макет RvShareDesktop): картинка для сторис, подпись к публикации, «Скачать картинку».
     Общее для учителя и админки. $shared — name, date, text, shareUrl; $pageLabel — адрес страницы с отзывами без протокола
     (или null); $close — метод Livewire или выражение ($set(…)) для закрытия. --}}
@php
    $call = '$wire.' . (str_contains($close, '(') ? $close : $close . '()');
@endphp
<x-ui.modal title="Поделиться отзывом" :sub="$shared['name'] . ' · ' . $shared['date']" width="l" :close="$close">
    <div class="flex flex-col gap-8 lg:flex-row" x-data="{ copied: false }">
        {{-- Картинку загружаем один раз в память: её же потом отдаёт «Скачать картинку» без ожидания --}}
        <img alt="Картинка с отзывом для сторис" data-src="{{ $shared['shareUrl'] }}"
             x-init="window.serdalPrefetchReviewCard($el.dataset.src).then(blob => $el.src = blob ? URL.createObjectURL(blob) : $el.dataset.src)"
             class="h-auto w-full rounded-lg bg-soft shadow-outline lg:w-sidebar lg:shrink-0">
        <div class="flex min-w-0 flex-1 flex-col gap-6">
            <div class="flex flex-col gap-1">
                <span class="text-t1 font-medium">Картинка для сторис</span>
                <span class="text-t2 text-muted">1080 × 1920 · Telegram, ВКонтакте, WhatsApp</span>
            </div>
            <div class="flex flex-col gap-2">
                <span class="text-t2 font-medium">Подпись к публикации</span>
                <div class="flex flex-col gap-2 rounded-lg bg-soft p-4" x-ref="caption">
                    <span class="whitespace-pre-line text-t1-s">«{{ $shared['text'] }}»</span>
                    <span class="text-t2 text-muted">— {{ $shared['name'] }}.@if ($pageLabel) Все отзывы: {{ $pageLabel }}@endif</span>
                </div>
                <div class="flex">
                    <x-ui.btn size="s" x-show="! copied" x-on:click="navigator.clipboard.writeText($refs.caption.innerText.trim()).then(() => copied = true)">Скопировать текст</x-ui.btn>
                    <x-ui.badge tone="ok" x-show="copied" x-cloak>Текст скопирован</x-ui.badge>
                </div>
            </div>
        </div>
    </div>
    <x-slot:footer>
        <x-ui.btn wire:click="{{ $close }}">Отмена</x-ui.btn>
        @php
            $deliver = "window.serdalShareReviewCard(" . Js::from($shared['shareUrl']) . ").then(r => { if (r === 'retry') return; if (r === 'download') \$dispatch('toast', { message: 'Картинка сохранена в «Загрузки»' }); {$call} })";
        @endphp
        <x-ui.btn variant="primary" icon="download" class="hidden lg:inline-flex" x-on:click="{{ $deliver }}">Скачать картинку</x-ui.btn>
        <x-ui.btn variant="primary" icon="share" class="lg:hidden" x-on:click="{{ $deliver }}">Поделиться</x-ui.btn>
    </x-slot:footer>
</x-ui.modal>
