{{-- Экран демо-кабинета: настоящая раскладка и настоящий шаблон экрана с выдуманными данными (App\Demo). --}}
<x-layouts.cabinet :title="$screen->title" :active="$screen->active" :bare="$screen->bare" :demo="$demo">
    @include($screen->view, $data)
</x-layouts.cabinet>
