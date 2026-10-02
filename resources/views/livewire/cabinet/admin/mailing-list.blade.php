<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head :title="$list->name" :sub="$facts" :back="route('cabinet.admin.mailings', ['tab' => 'lists'])" back-label="Рассылки">
        <x-slot:actions>
            <x-ui.btn variant="primary" icon="upload" wire:click="openImport">Добавить адреса</x-ui.btn>
            <x-ui.menu label="Действия со списком">
                <x-ui.menu-item wire:click="rename">Переименовать</x-ui.menu-item>
                <x-ui.menu-item wire:click="askDelete">Удалить список</x-ui.menu-item>
            </x-ui.menu>
        </x-slot:actions>
    </x-ui.page-head>

    @if ($total === 0)
        <x-ui.card>
            <x-ui.empty icon="mail" title="В списке пока нет адресов" text="Загрузите таблицу Excel или .csv с колонкой почты — название школы и город подхватим из соседних колонок. Или добавьте учителей и учеников платформы.">
                <x-slot:action>
                    <x-ui.btn icon="upload" wire:click="openImport">Загрузить таблицу</x-ui.btn>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @else
        <x-ui.card class="gap-4" aria-label="Адреса">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <x-ui.search placeholder="Почта, школа или город" wire:model.live.debounce.400ms="search" />
                @if ($hasUnsubscribed)
                    <x-ui.seg fit :items="['all' => 'Все', 'unsubscribed' => 'Отписались']" model="filter" :active="$filter" aria-label="Какие адреса показать" />
                @endif
            </div>

            <div class="flex flex-col">
                @forelse ($contacts as $c)
                    <x-ui.row wire:key="mct-{{ $c['id'] }}" class="first:border-t-0 first:pt-0">
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="truncate text-t1 font-medium">{{ $c['email'] }}</span>
                            @if ($c['sub'])<span class="truncate text-t2 text-muted">{{ $c['sub'] }}</span>@endif
                        </div>
                        @if ($c['unsubscribed'])<x-ui.badge>Отписался</x-ui.badge>@endif
                        <x-ui.menu label="Действия с адресом {{ $c['email'] }}">
                            <x-ui.menu-item wire:click="remove({{ $c['id'] }})">Убрать из списка</x-ui.menu-item>
                        </x-ui.menu>
                    </x-ui.row>
                @empty
                    <p class="text-t2 text-muted">Ничего не нашли — попробуйте другой запрос</p>
                @endforelse
            </div>

            @if ($more > 0)
                <button type="button" wire:click="more" class="link self-start text-t1-s">Показать ещё {{ min($more, 50) }}</button>
            @endif
        </x-ui.card>
    @endif

    @if ($importing)
        <x-ui.modal title="Добавить адреса" sub="В строке — почта, название школы и город" close="closeImport">
            @if ($importResult)
                <p class="text-t1-s">{{ $importResult['summary'] }}.</p>
                <div class="flex flex-col gap-2">
                    <span class="text-t2 font-medium">Не нашли почту в строках</span>
                    <ul class="flex flex-col gap-1 text-t2 text-muted">
                        @foreach ($importResult['invalid'] as $line)
                            <li class="truncate">{{ $line }}</li>
                        @endforeach
                    </ul>
                    @if ($importResult['more'])
                        <span class="text-t3 text-muted">и ещё {{ plural_ru($importResult['more'], 'строка', 'строки', 'строк') }}</span>
                    @endif
                </div>
                <x-slot:footer>
                    <x-ui.btn variant="primary" wire:click="closeImport">Готово</x-ui.btn>
                </x-slot:footer>
            @else
                <x-ui.seg :items="['file' => 'Из файла', 'paste' => 'Вставить', 'users' => 'Пользователи']" model="importMode" :active="$importMode" aria-label="Откуда взять адреса" />

                @if ($importMode === 'users')
                    <div class="flex flex-col gap-2">
                        <span class="text-t2 font-medium">Кого добавить</span>
                        <x-ui.seg fit :items="$users['groups']" model="userGroup" :active="$userGroup" aria-label="Кого добавить" />
                    </div>
                    @if ($userGroup === 'tutors')
                        <div class="flex flex-col gap-2">
                            <span class="text-t2 font-medium">Тариф</span>
                            <x-ui.seg fit :items="$users['tariffs']" model="tariff" :active="$tariff" aria-label="Тариф учителей" />
                        </div>
                    @elseif ($userGroup === 'people')
                        <x-ui.person-select label="Люди" name="userIds" :people="$users['people']" action="pickUser" :checked="$userIds" placeholder="Выбрать учителей и учеников" />
                        @if ($users['chosen'])
                            <div class="flex flex-wrap gap-2">
                                @foreach ($users['chosen'] as $person)
                                    <x-ui.token :remove="'dropUser(' . $person['id'] . ')'" :label="$person['name']" wire:key="mlu-{{ $person['id'] }}">{{ $person['name'] }}</x-ui.token>
                                @endforeach
                            </div>
                        @endif
                    @endif
                    @error('userIds')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                    <span class="text-t2 text-muted">
                        @if ($users['count'])
                            Добавим {{ plural_ru($users['count'], 'адрес', 'адреса', 'адресов') }} — почту, имя и роль из профиля. Заблокированных не добавляем
                        @else
                            {{ $userGroup === 'people' ? 'Найдите людей по имени или почте' : 'Таких пользователей пока нет' }}
                        @endif
                    </span>
                @elseif ($importMode === 'paste')
                    <x-ui.field label="Адреса" name="pasted" :rows="8" wire:model="pasted" placeholder="info@school5.ru    Школа №5    Казань" hint="Скопируйте колонки из таблицы — каждый адрес с новой строки" />
                @else
                    <div class="flex flex-col gap-2">
                        <x-ui.dropzone wire:model="file" :multiple="false" accept=".xlsx,.xls,.csv,.txt" title="Перетащите таблицу или выберите файл" hint="Excel (.xlsx) или .csv, до 10 МБ. Почту найдём в любой колонке" aria-label="Файл с адресами" />
                        <span wire:loading wire:target="file" class="text-t2 text-muted">Загружаем файл…</span>
                        @if ($file)
                            <span wire:loading.remove wire:target="file" class="flex items-center gap-2 text-t2"><x-ui.icon name="attach" size="s" class="text-muted" />{{ $file->getClientOriginalName() }}</span>
                        @endif
                        @error('file')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                    </div>
                @endif

                <x-slot:note>{{ $importMode === 'users' ? 'Кто уже есть в списке, пропустим' : 'Повторы и адреса, которые уже есть в списке, пропустим' }}</x-slot:note>
                <x-slot:footer>
                    <x-ui.btn wire:click="closeImport">Отмена</x-ui.btn>
                    <x-ui.btn variant="primary" wire:click="import" wire:loading.attr="disabled" wire:target="import,file">Добавить</x-ui.btn>
                </x-slot:footer>
            @endif
        </x-ui.modal>
    @endif

    @if ($renaming)
        <x-ui.modal title="Переименовать список" close="closeRename" width="s">
            <x-ui.field label="Название" name="name" wire:model="name" wire:keydown.enter="saveName" />
            <x-slot:footer>
                <x-ui.btn wire:click="closeRename">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveName">Сохранить</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($confirmDelete)
        <x-ui.modal title="Удалить список?" :sub="$list->name" close="closeDelete" width="s">
            <p class="text-t1-s">Адреса пропадут из списка. Письма, которые уже ушли, и их статистика останутся, отписавшиеся по-прежнему не получат рассылок.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="closeDelete">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="delete">Удалить список</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
