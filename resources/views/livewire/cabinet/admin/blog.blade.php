<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Блог" :sub="$facts">
        <x-slot:actions>
            <a href="{{ route('blog.index') }}" target="_blank" rel="noopener" class="link hidden text-t1-s lg:inline">Блог на сайте</a>
            <x-ui.btn variant="dark" icon="plus" :href="route('cabinet.admin.blog-article', ['post' => 'new'])">Написать статью</x-ui.btn>
        </x-slot:actions>
    </x-ui.page-head>

    <div class="flex flex-col gap-6">
        <x-ui.tabs :items="$tabs" model="tab" :active="$tab" aria-label="Статьи блога" />

        <x-ui.card class="gap-0" aria-label="{{ $tabs[$tab] }}">
            @forelse ($items as $item)
                <x-ui.row :href="route('cabinet.admin.blog-article', ['post' => $item['id']])" wire:key="bp-{{ $item['id'] }}" class="first:border-t-0 first:pt-0">
                    <x-ui.text :title="$item['title']" :sub="$item['meta']" />
                    @if ($item['views'])<span class="hidden shrink-0 text-t2 text-muted lg:inline">{{ $item['views'] }}</span>@endif
                </x-ui.row>
            @empty
                <p class="text-t2 text-muted">{{ $empty }}</p>
            @endforelse
        </x-ui.card>
    </div>
</div>
