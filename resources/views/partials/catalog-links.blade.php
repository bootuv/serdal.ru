{{--
  Ссылки на страницы каталога в мятном контейнере — как карточки шагов на главной и блоки «О нас».
  $groups = [['title' => ..., 'links' => [['name' => ..., 'url' => ..., 'count' => ...]]]], $heading — необязательный заголовок.
--}}
@php
  $groups = array_values(array_filter($groups, fn ($group) => !empty($group['links'])));
@endphp
@if($groups)
  <section class="catalog-panel {{ $class ?? '' }}" @isset($id) id="{{ $id }}" @endisset>
    <div class="catalog-panel__box">
      @isset($heading)
        <h2 class="h3 catalog-panel__heading">{{ $heading }}</h2>
      @endisset
      @foreach($groups as $group)
        <div class="catalog-panel__group">
          @if(!empty($group['title']))
            <h3 class="catalog-panel__title p24">{{ $group['title'] }}</h3>
          @endif
          <ul class="catalog-panel__list" role="list">
            @foreach($group['links'] as $link)
              <li><a href="{{ $link['url'] }}" class="catalog-panel__link p18">{{ $link['name'] }} <span class="catalog-panel__count">{{ $link['count'] }}</span></a></li>
            @endforeach
          </ul>
        </div>
      @endforeach
      @isset($more)
        <p class="catalog-panel__more p18"><a href="{{ $more['url'] }}">{{ $more['name'] }}</a></p>
      @endisset
    </div>
  </section>
@endif
