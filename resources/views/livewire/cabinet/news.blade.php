<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Новости" :sub="$unread ? plural_ru($unread, 'непрочитанная', 'непрочитанные', 'непрочитанных') : null">
        @if ($unread > 1)
            <x-slot:actions>
                <x-ui.btn wire:click="readAll">Отметить все прочитанными</x-ui.btn>
            </x-slot:actions>
        @endif
    </x-ui.page-head>

    @if ($items->isEmpty())
        <x-ui.card>
            <x-ui.empty icon="news" title="Новостей пока нет" text="Здесь появятся новости платформы и обращения администрации" />
        </x-ui.card>
    @else
        <x-ui.card class="max-w-text gap-0" aria-label="Новости">
            <x-ui.list>
                @foreach ($items as $item)
                    <x-ui.row :href="$item['url']" align="start" wire:key="news-{{ $item['id'] }}" class="first:border-t-0 first:pt-0">
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <div class="flex items-center gap-2">
                                @if ($item['unread'])<span class="size-2 shrink-0 rounded-full bg-danger" aria-label="Не прочитана"></span>@endif
                                <span @class(['min-w-0 text-t1', 'font-semibold' => $item['unread'], 'font-medium' => ! $item['unread']])>{{ $item['title'] }}</span>
                            </div>
                            @if ($item['excerpt'] !== '')<span class="line-clamp-2 text-t2 text-muted">{{ $item['excerpt'] }}</span>@endif
                            <span class="text-t3 text-muted">{{ $item['when'] }}@if ($item['pinned']) · закреплена@endif</span>
                        </div>
                    </x-ui.row>
                @endforeach
            </x-ui.list>
            @if ($hasMore)
                <x-ui.btn size="s" wire:click="more" class="mt-4 self-start">Показать ещё</x-ui.btn>
            @endif
        </x-ui.card>
    @endif
</div>
