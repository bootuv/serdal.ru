<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Новости" :sub="$facts">
        <x-slot:actions>
            <x-ui.btn variant="dark" icon="plus" :href="route('cabinet.admin.news-item', ['announcement' => 'new'])">Написать новость</x-ui.btn>
        </x-slot:actions>
    </x-ui.page-head>

    <div class="flex flex-col gap-6">
        <x-ui.tabs :items="$tabs" model="tab" :active="$tab" aria-label="Новости" />

        <x-ui.card class="gap-0" aria-label="{{ $tabs[$tab] }}">
            @forelse ($items as $item)
                <x-ui.row :href="route('cabinet.admin.news-item', ['announcement' => $item['id']])" wire:key="an-{{ $item['id'] }}" class="first:border-t-0 first:pt-0">
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="truncate text-t1 font-medium">{{ $item['title'] }}</span>
                        <span class="text-t2 text-muted">{{ $item['meta'] }}</span>
                    </div>
                    @if ($item['important'])<x-ui.badge>Важная</x-ui.badge>@endif
                    @if ($item['reads'])<span class="hidden shrink-0 text-t2 text-muted lg:inline">{{ $item['reads'] }}</span>@endif
                </x-ui.row>
            @empty
                <p class="text-t2 text-muted">{{ $empty }}</p>
            @endforelse
        </x-ui.card>
    </div>
</div>
