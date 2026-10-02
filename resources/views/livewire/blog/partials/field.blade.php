{{-- Поле комментария с эмодзи: кнопка-смайлик открывает набор, эмодзи вставляется туда, где стоит курсор.
     Параметры: model (свойство Livewire), submit (метод для Ctrl/⌘ + Enter), rows, placeholder, label, autofocus. --}}
@php($emoji = [
    // реакции
    '👍', '👏', '🙏', '❤️', '🔥', '🎉', '💯', '🙌', '💪', '🤝', '✅', '👀',
    // настроение
    '😊', '🙂', '😍', '🥰', '😂', '😅', '😎', '🤗', '🤔', '😮', '😢', '👋',
    // учеба
    '📚', '📖', '✏️', '🖊️', '📝', '📐', '🧮', '🔬', '🧪', '🌍', '🧠', '💡',
    // праздник и благодарность
    '💐', '🌹', '🌷', '🌸', '🎁', '🏆', '🥇', '⭐', '🎓', '🎯', '🚀', '☕',
])
<div class="blog-field" x-data="{
        emojiOpen: false,
        insert(e) {
            const t = $refs.input, s = t.selectionStart ?? t.value.length, f = t.selectionEnd ?? t.value.length;
            t.value = t.value.slice(0, s) + e + t.value.slice(f);
            t.selectionStart = t.selectionEnd = s + e.length;
            t.dispatchEvent(new Event('input', { bubbles: true }));
            t.focus();
        },
     }" x-on:click.outside="emojiOpen = false" x-on:keydown.escape="emojiOpen = false">
    <textarea x-ref="input" wire:model="{{ $model }}" rows="{{ $rows ?? 3 }}" maxlength="{{ \App\Services\BlogCommentService::MAX_LENGTH }}"
              placeholder="{{ $placeholder ?? '' }}" aria-label="{{ $label }}" {{ ($autofocus ?? false) ? 'autofocus' : '' }}
              x-on:keydown.meta.enter="$wire.{{ $submit }}()" x-on:keydown.ctrl.enter="$wire.{{ $submit }}()"></textarea>
    <button type="button" class="blog-emoji-toggle" x-on:click="emojiOpen = ! emojiOpen" x-bind:aria-expanded="emojiOpen" aria-label="Эмодзи" title="Эмодзи">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8.5 14.5a4.5 4.5 0 0 0 7 0M9 9.5h.01M15 9.5h.01"/></svg>
    </button>
    <div class="blog-emoji" x-show="emojiOpen" x-cloak role="listbox" aria-label="Эмодзи">
        @foreach($emoji as $e)
            <button type="button" role="option" x-on:click="insert(@js($e))" aria-label="{{ $e }}">{{ $e }}</button>
        @endforeach
    </div>
</div>
