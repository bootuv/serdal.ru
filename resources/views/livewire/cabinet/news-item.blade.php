<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head :title="$title" :sub="$when" :back="$backUrl" back-label="Новости" />

    <x-ui.card as="article" class="max-w-text" aria-label="{{ $title }}">
        @if ($body)
            <div class="block-content">{{ $body }}</div>
        @else
            <p class="text-t2 text-muted">Текста нет — вся новость в заголовке</p>
        @endif
    </x-ui.card>
</div>
