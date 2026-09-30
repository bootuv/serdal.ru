{{-- Выбор человека из длинного списка (ученик в занятии, профиль основателя): x-ui.search-select с аватаром, именем и почтой в строках,
     поиск — по имени и почте. people: [['id' => …, 'name' => …, 'email' => …], …].
     model — свойство Livewire с id выбранного (один человек; selected — его id); action — метод Livewire, получает id
     (выбор нескольких: список не закрывается; checked — id уже выбранных, они отмечены галочкой; keep="false" — закрыть после выбора). --}}
@props(['people', 'name', 'label' => null, 'model' => null, 'action' => null, 'selected' => null, 'checked' => null, 'keep' => null, 'placeholder' => 'Выберите человека', 'search' => 'Имя или почта'])
<x-ui.search-select avatars :name="$name" :label="$label" :model="$model" :action="$action" :selected="$selected" :checked="$checked" :keep="$keep" :placeholder="$placeholder" :search="$search"
    :options="collect($people)->map(fn ($p) => ['value' => (string) $p['id'], 'title' => $p['name'], 'sub' => $p['email'] ?? null])->all()" />
