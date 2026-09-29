{{-- contents: без новости не оставляет пустого места в сетке экрана --}}
<div class="contents">
    @if ($news)
        <x-ui.card news class="lg:flex-row lg:items-center lg:justify-between lg:gap-6" aria-labelledby="news-banner-{{ $news['id'] }}">
            <div class="flex min-w-0 flex-col gap-1">
                <span class="flex items-center gap-2 text-t2 text-muted"><x-ui.icon name="news" size="s" />Новость от команды Serdal</span>
                <a id="news-banner-{{ $news['id'] }}" href="{{ $news['url'] }}" class="text-t1 font-semibold hover:underline hover:underline-offset-4">{{ $news['title'] }}</a>
                @if ($news['excerpt'] !== '')<span class="line-clamp-2 text-t2 text-muted">{{ $news['excerpt'] }}</span>@endif
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <x-ui.btn :href="$news['url']">Читать</x-ui.btn>
                <x-ui.btn square icon="x" wire:click="dismiss({{ $news['id'] }})" aria-label="Скрыть новость" />
            </div>
        </x-ui.card>
    @endif
</div>
