{{-- «Поделиться» новым отзывом. На компьютере — окно с картинкой (RvShareDesktop); на телефоне — системное окно
     «Поделиться» (RvShareMobile) и рядом «Скопировать текст». Оба варианта отмечают отзыв прочитанным. $r — отзыв. --}}
<x-ui.btn size="s" icon="share" wire:click="share({{ $r['id'] }})" class="hidden lg:inline-flex">Поделиться</x-ui.btn>
<x-ui.btn size="s" icon="share" class="lg:hidden"
          x-on:pointerdown="window.serdalPrefetchReviewCard({{ Js::from($r['shareUrl']) }})"
          x-on:click="$wire.shared({{ $r['id'] }}); window.serdalShareReviewCard({{ Js::from($r['shareUrl']) }}, () => $wire.share({{ $r['id'] }}))">Поделиться</x-ui.btn>
<x-ui.copy :value="$r['caption']" message="Текст скопирован" size="s" class="lg:hidden">Скопировать текст</x-ui.copy>
