<button type="button" wire:click="toggle" wire:loading.attr="disabled" @class(['blog-like', 'active' => $liked]) aria-pressed="{{ $liked ? 'true' : 'false' }}" aria-label="{{ $liked ? 'Убрать лайк' : 'Нравится' }}">
    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/></svg>
    <span>{{ $count > 0 ? $count : 'Нравится' }}</span>
</button>
