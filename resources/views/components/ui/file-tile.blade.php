{{-- Плитка файла 40: превью картинки (thumb), иначе тип (PDF, DOC, MP3, IMG…) по расширению или иконка.
     onMint — белая плитка внутри фокус-блока. --}}
@props(['name' => null, 'icon' => null, 'onMint' => false, 'thumb' => null])
@php
    $ext = $name ? mb_strtolower(pathinfo($name, PATHINFO_EXTENSION)) : '';
    $label = match (true) {
        in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic']) => 'IMG',
        in_array($ext, ['doc', 'docx', 'odt', 'rtf']) => 'DOC',
        in_array($ext, ['xls', 'xlsx', 'csv']) => 'XLS',
        in_array($ext, ['ppt', 'pptx', 'key']) => 'PPT',
        in_array($ext, ['mp3', 'wav', 'm4a', 'ogg']) => 'MP3',
        in_array($ext, ['mp4', 'mov', 'webm']) => 'MP4',
        $ext !== '' => mb_strtoupper(mb_substr($ext, 0, 3)),
        default => '',
    };
@endphp
@if ($thumb)
<img src="{{ $thumb }}" alt="" loading="lazy" {{ $attributes->class(['size-10 shrink-0 rounded object-cover', 'bg-white' => $onMint, 'bg-soft' => ! $onMint]) }}>
@else
<span {{ $attributes->class(['flex size-10 shrink-0 items-center justify-center rounded text-count font-semibold text-muted', 'bg-white' => $onMint, 'bg-soft' => ! $onMint]) }}>
    @if ($icon)<x-ui.icon :name="$icon" size="s" class="text-ink" />@else{{ $label }}@endif
</span>
@endif
