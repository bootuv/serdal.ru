<div class="blog-follow">
    @unless($self)
        <button type="button" wire:click="toggle" wire:loading.attr="disabled" @class(['blog-follow-button', 'active' => $following]) aria-pressed="{{ $following ? 'true' : 'false' }}">
            @if($following)
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7"/></svg>Вы подписаны
            @else
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Подписаться
            @endif
        </button>
    @endunless
    @if($count !== null)
        <span class="blog-follow-count">{{ plural_ru($count, 'подписчик', 'подписчика', 'подписчиков') }}</span>
    @endif
</div>
