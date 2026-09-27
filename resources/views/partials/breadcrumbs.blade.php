{{-- Хлебные крошки публичных страниц: $items = [['name' => ..., 'url' => ...], ...], последний пункт — текущая страница --}}
<nav class="breadcrumbs p18" aria-label="Навигация">
  <a href="/">Главная</a>
  @foreach($items as $item)
    <span class="breadcrumbs__sep" aria-hidden="true">/</span>
    @if($loop->last)
      <span aria-current="page">{{ $item['name'] }}</span>
    @else
      <a href="{{ $item['url'] }}">{{ $item['name'] }}</a>
    @endif
  @endforeach
</nav>
