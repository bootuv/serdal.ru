{{-- Зона загрузки файлов: пунктирная рамка, иконка, подсказка. Внутри — скрытый input (атрибуты передаются в него, например wire:model). --}}
@props(['hint' => null, 'multiple' => true, 'accept' => null])
<label class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border border-dashed border-line-strong px-6 py-8 text-center hover:border-ink">
    <x-ui.icon name="upload" class="text-muted" />
    <span class="text-t1-s font-medium">Перетащите файлы или <span class="underline underline-offset-4">выберите</span></span>
    @if ($hint)<span class="text-t3 text-muted">{{ $hint }}</span>@endif
    <input type="file" class="sr-only" @if($multiple) multiple @endif @if($accept) accept="{{ $accept }}" @endif {{ $attributes }}>
</label>
