<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Блог" :sub="$facts">
        <x-slot:actions>
            <a href="{{ route('cabinet.admin.blog-stats') }}" class="link mr-4 text-t1-s">Статистика</a>
            <a href="{{ route('blog.index') }}" target="_blank" rel="noopener" class="link mr-4 hidden text-t1-s lg:inline">Блог на сайте</a>
            <x-ui.btn variant="dark" icon="plus" :href="route('cabinet.admin.blog-article', ['post' => 'new'])">Написать статью</x-ui.btn>
        </x-slot:actions>
    </x-ui.page-head>

    <div class="flex flex-col gap-6">
        <x-ui.tabs :items="$tabs" model="tab" :active="$tab" :counts="$counts" aria-label="Статьи блога" />

        @if ($tab === 'reports')
            <x-ui.card class="gap-0" aria-label="Жалобы">
                @forelse ($reports as $c)
                    <x-ui.row wire:key="br-{{ $c->id }}" class="first:border-t-0 first:pt-0" align="start">
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="text-t1-s font-medium">{{ $c->user?->name ?? 'Удаленный пользователь' }} · <a href="{{ route('blog.show', $c->post->slug) }}#comment-{{ $c->id }}" target="_blank" rel="noopener" class="link">{{ $c->post->title }}</a></span>
                            <p class="text-t2">{{ \Illuminate\Support\Str::limit((string) $c->body, 400) }}</p>
                            <span class="text-t2 text-muted">{{ plural_ru($c->open_reports, 'жалоба', 'жалобы', 'жалоб') }}@if ($c->reports->pluck('reason')->filter()->isNotEmpty()): {{ $c->reports->pluck('reason')->filter()->implode('; ') }}@endif</span>
                        </div>
                        <x-ui.menu label="Что сделать с комментарием">
                            <x-ui.menu-item wire:click="deleteComment({{ $c->id }})">Удалить комментарий</x-ui.menu-item>
                            <x-ui.menu-item wire:click="dismissReports({{ $c->id }})">Оставить, жалобы отклонить</x-ui.menu-item>
                        </x-ui.menu>
                    </x-ui.row>
                @empty
                    <p class="text-t2 text-muted">{{ $empty }}</p>
                @endforelse
            </x-ui.card>
        @elseif ($tab === 'tags')
            <x-ui.card class="gap-0" aria-label="Теги">
                @forelse ($tags as $t)
                    <x-ui.row wire:key="bt-{{ $t->id }}" class="first:border-t-0 first:pt-0">
                        <x-ui.text :title="$t->name" :sub="plural_ru($t->published_count, 'статья на сайте', 'статьи на сайте', 'статей на сайте') . ($t->posts_count > $t->published_count ? ' · еще не опубликовано ' . ($t->posts_count - $t->published_count) : '')" />
                        <x-ui.menu label="Действия с тегом «{{ $t->name }}»">
                            <x-ui.menu-item wire:click="editTag({{ $t->id }})">Переименовать</x-ui.menu-item>
                            <x-ui.menu-item wire:click="askDeleteTag({{ $t->id }})">Удалить</x-ui.menu-item>
                        </x-ui.menu>
                    </x-ui.row>
                @empty
                    <p class="text-t2 text-muted">{{ $empty }}</p>
                @endforelse
            </x-ui.card>
        @else
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
        @endif
    </div>

    @if ($editingTag)
        <x-ui.modal title="Переименовать тег" close="closeTag" width="s">
            <x-ui.field label="Название" name="tagName" wire:model="tagName" wire:keydown.enter="saveTag" hint="Если такой тег уже есть, теги объединятся" />
            <x-slot:footer>
                <x-ui.btn wire:click="closeTag">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveTag">Сохранить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($deleting)
        <x-ui.modal title="Удалить тег?" :sub="$deleting->name" close="closeDeleteTag" width="s">
            <p class="text-t1-s">Тег пропадет у всех статей и из блога на сайте. Сами статьи останутся.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="closeDeleteTag">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="deleteTag">Удалить тег</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
