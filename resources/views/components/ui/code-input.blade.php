{{-- Код из письма: 6 ячеек по цифре, вставка кода целиком, стрелки и Backspace между ячейками.
     model — свойство Livewire, куда пишется код; hint — подсказка под ячейками, пока нет ошибки. --}}
@props(['model' => 'verification_code', 'label' => 'Код из письма', 'hint' => null, 'id' => 'code'])
<div class="flex flex-col gap-2"
     x-data="{
         d: ['', '', '', '', '', ''],
         focus(i) { const el = this.$refs['c' + Math.max(0, Math.min(5, i))]; el.focus(); el.select(); },
         sync() { this.$wire.$set(@js($model), this.d.join(''), false); },
         put(i, value) {
             const digits = String(value).replace(/\D/g, '').split('');
             if (digits.length === 0) { this.d[i] = ''; this.sync(); return; }
             const start = digits.length >= 6 ? 0 : i;
             digits.slice(0, 6 - start).forEach((c, k) => this.d[start + k] = c);
             this.sync();
             this.focus(start + digits.length);
         },
         erase(i, e) {
             if (this.d[i] !== '' || i === 0) return;
             e.preventDefault();
             this.d[i - 1] = '';
             this.sync();
             this.focus(i - 1);
         },
     }"
     x-init="$nextTick(() => focus(0))">
    <label for="{{ $id }}-0" class="text-t2 font-medium">{{ $label }}</label>
    <div class="flex gap-2" wire:ignore>
        @for ($i = 0; $i < 6; $i++)
            <input id="{{ $id }}-{{ $i }}" x-ref="c{{ $i }}" type="text" inputmode="numeric" autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}" aria-label="Цифра {{ $i + 1 }}"
                   x-bind:value="d[{{ $i }}]"
                   x-on:input="put({{ $i }}, $event.target.value); $event.target.value = d[{{ $i }}]"
                   x-on:paste.prevent="put({{ $i }}, $event.clipboardData.getData('text'))"
                   x-on:keydown.backspace="erase({{ $i }}, $event)"
                   x-on:keydown.arrow-left.prevent="focus({{ $i - 1 }})"
                   x-on:keydown.arrow-right.prevent="focus({{ $i + 1 }})"
                   x-on:focus="$event.target.select()"
                   class="field size-13 min-w-0 shrink px-0 text-center text-num font-medium">
        @endfor
    </div>
    @if ($errors->has($model))
        <span class="text-t2 font-medium text-danger-fg">{{ $errors->first($model) }}</span>
    @elseif ($hint)
        <span class="text-t3 text-muted">{{ $hint }}</span>
    @endif
</div>
