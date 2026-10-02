<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Мои статьи" :sub="$facts">
        <x-slot:actions>
            @if ($authorUrl)<a href="{{ $authorUrl }}" target="_blank" rel="noopener" class="link mr-4 hidden text-t1-s lg:inline">Мои статьи на сайте</a>@endif
            <x-ui.btn variant="dark" icon="plus" :href="route('cabinet.teacher.blog-article', ['post' => 'new'])">Написать статью</x-ui.btn>
        </x-slot:actions>
    </x-ui.page-head>

    @if ($items->isEmpty())
        <x-ui.card>
            <x-ui.empty icon="pencil" title="Напишите статью для блога Serdal" text="Расскажите о своем предмете, подготовке к экзаменам или о том, как вы занимаетесь. После проверки статья выйдет в блоге с вашим именем и ссылкой на вашу страницу — так ученики находят учителей.">
                <x-slot:action>
                    <x-ui.btn icon="plus" :href="route('cabinet.teacher.blog-article', ['post' => 'new'])">Написать статью</x-ui.btn>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @else
        <x-ui.card class="gap-0" aria-label="Мои статьи">
            @foreach ($items as $item)
                <x-ui.row :href="route('cabinet.teacher.blog-article', ['post' => $item['id']])" wire:key="tb-{{ $item['id'] }}" class="first:border-t-0 first:pt-0">
                    <div class="flex min-w-0 flex-1 basis-1/2 flex-col gap-1">
                        <span class="line-clamp-2 text-t1 font-medium sm:truncate">{{ $item['title'] }}</span>
                        <span class="text-t2 text-muted">{{ $item['meta'] }}</span>
                        @if ($item['badge'])<x-ui.badge :tone="$item['badge'][0]" class="self-start sm:hidden">{{ $item['badge'][1] }}</x-ui.badge>@endif
                    </div>
                    @if ($item['badge'])<x-ui.badge :tone="$item['badge'][0]" class="hidden sm:inline-flex">{{ $item['badge'][1] }}</x-ui.badge>@endif
                </x-ui.row>
            @endforeach
        </x-ui.card>
    @endif
</div>
