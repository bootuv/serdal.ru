<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Рассылки">
        <x-slot:actions>
            @if ($tab === 'lists')
                <x-ui.btn variant="dark" icon="plus" wire:click="newList">Новый список</x-ui.btn>
            @else
                <x-ui.btn variant="dark" icon="plus" :href="route('cabinet.admin.mailing', ['campaign' => 'new'])">Написать письмо</x-ui.btn>
            @endif
        </x-slot:actions>
    </x-ui.page-head>

    <div class="flex flex-col gap-6">
        <x-ui.tabs :items="$tabs" model="tab" :active="$tab" aria-label="Рассылки" />

        @if ($tab === 'lists')
            <x-ui.card class="gap-0" aria-label="Списки">
                @forelse ($lists as $list)
                    <x-ui.row :href="route('cabinet.admin.mailing-list', ['list' => $list['id']])" wire:key="ml-{{ $list['id'] }}" class="first:border-t-0 first:pt-0">
                        <x-ui.text :title="$list['name']" :sub="$list['meta']" />
                    </x-ui.row>
                @empty
                    <p class="text-t2 text-muted">Списков пока нет — создайте список и загрузите в него адреса из таблицы</p>
                @endforelse
            </x-ui.card>
        @else
            <x-ui.card class="gap-0" aria-label="Письма">
                @forelse ($letters as $item)
                    <x-ui.row :href="route('cabinet.admin.mailing', ['campaign' => $item['id']])" wire:key="mc-{{ $item['id'] }}" class="first:border-t-0 first:pt-0">
                        <x-ui.text :title="$item['subject']" :sub="$item['meta']" />
                        @if ($item['problem'])<x-ui.badge tone="danger">Отправка стоит</x-ui.badge>@endif
                        @if ($item['opened'])<span class="hidden shrink-0 text-t2 text-muted lg:inline">{{ $item['opened'] }}</span>@endif
                    </x-ui.row>
                @empty
                    <p class="text-t2 text-muted">Писем пока нет — напишите первое и выберите, каким спискам его отправить</p>
                @endforelse
            </x-ui.card>
        @endif
    </div>

    @if ($creatingList)
        <x-ui.modal title="Новый список" close="closeList" width="s">
            <x-ui.field label="Название" name="listName" wire:model="listName" wire:keydown.enter="createList" placeholder="Например, «Школы Казани»" />
            <x-slot:footer>
                <x-ui.btn wire:click="closeList">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="createList" wire:loading.attr="disabled" wire:target="createList">Создать список</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
