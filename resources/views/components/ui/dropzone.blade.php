{{-- Зона загрузки файлов: пунктирная рамка, иконка, подсказка. Прозрачный input растянут на всю зону —
     файлы можно перетащить или выбрать по клику. Атрибуты (wire:model, aria-label) передаются в input.
     onMint — белый фон внутри фокус-блока. --}}
@props(['hint' => null, 'title' => null, 'multiple' => true, 'accept' => null, 'onMint' => false])
<div @class(['relative flex flex-col items-center gap-2 rounded-lg border border-dashed border-line-strong px-6 py-8 text-center hover:border-ink focus-within:border-ink', 'bg-white' => $onMint])>
    <x-ui.icon name="upload" class="text-muted" />
    <span class="text-t1-s font-medium">{{ $title ?? 'Перетащите файлы или выберите' }}</span>
    @if ($hint)<span class="text-t3 text-muted">{{ $hint }}</span>@endif
    <input type="file" class="absolute inset-0 size-full cursor-pointer opacity-0" @if($multiple) multiple @endif @if($accept) accept="{{ $accept }}" @endif {{ $attributes }}>
</div>
