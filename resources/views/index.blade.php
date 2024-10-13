@extends('layout')

@section('content')
  <section class="header">
    <a href="index.html" aria-current="page" class="logo-wrapper w-inline-block w--current"><img src="images/Logo.svg" loading="lazy" width="Auto" height="32" alt="" class="logo"></a>
    <div class="main-menu">
      <a href="#specialists" target="_blank" class="p24">Все специалисты</a>
      <a href="#" target="_blank" class="p24">О нас</a>
      <a href="#" target="_blank" class="p24">Отзывы</a>
    </div>
    <a href="https://room.serdal.ru/signin" target="_blank" class="p24 login">Войти</a>
  </section>
  <section class="intro-wrapper">
    <div class="intro">
      <div class="video-background-section">
        <div class="video-wrapper">
          <div class="video-embed w-embed w-iframe"><iframe src="https://player.vimeo.com/video/1005192725?background=1&quality=1080p" width="100%" height="100%" frameborder="0" allow="autoplay; fullscreen" allowfullscreen=""></iframe></div>
        </div>
      </div>
      <div class="video-gradient"></div>
      <div class="title-block">
        <div class="title-description">
          <h1 class="h1 white-text">Онлайн репетиторы в Ингушетии</h1>
          <div class="p30 white-text">Первая интерактивная онлайн-платформа профессиональных репетиторов и менторов в Ингушетии.</div>
        </div>
      </div>
      <div class="main-actions">
        <a href="#specialists" class="main-button search-tutor w-button">Найти специалиста</a>
        <a data-w-id="79cbc6fb-829e-0080-5bfd-8999f35ce137" href="#" class="trailer-button w-inline-block">
          <div class="button-play"><img src="images/Frame-2.png" loading="lazy" width="76" height="76" alt="" class="trailer-button-thumb"><img src="images/play.svg" loading="lazy" alt="" class="play-icon">
            <div class="dark-opacity"></div>
          </div>
          <div class="trailet-button-text">Трейлер</div>
        </a>
      </div>
    </div>
  </section>
  <section class="directions-row">
    <ul role="list" class="directions">
      @foreach(App\Models\Direct::all() as $direct)
      <li class="direction">
        <div class="p30">{{ $direct->name }}</div>
      </li>
      @endforeach 
    </ul>
  </section>
  <section class="steps_wrapper">
    <div class="step">
      <h1 class="step-count">1</h1>
      <div class="step-text">
        <h3 class="h3">Выберите специалиста</h3>
        <p class="p24">У нас вы найдете самых опытных преподавателей Ингушетии с большим стажем и отличной профессиональной репутацией.</p>
      </div>
    </div>
    <div class="step">
      <h1 class="step-count">2</h1>
      <div class="step-text">
        <h3 class="h3">Свяжитесь<br>с ним</h3>
        <p class="p24">На странице специалиста вы можете найти контакты, через которые можно связаться с ним, обсудить детали и записаться на занятия.</p>
      </div>
    </div>
    <div class="step">
      <h1 class="step-count">3</h1>
      <div class="step-text">
        <h3 class="h3">Приходите на онлайн занятия</h3>
        <p class="p24">Учитесь не выходя из дома. На нашей онлайн-платформе занятия не уступают по качеству живым урокам в классе, а где-то даже превосходят.</p>
      </div>
    </div>
  </section>
  <section class="specialists">
    <h2 class="h2">Найти специалиста</h2>
    <h2 id="specialists" class="h2">Найти специалиста</h2>
    <div class="filters-wrapper">
      <div class="filters">
        <div data-hover="false" data-delay="0" class="filter w-dropdown">
          <div class="filter-select w-dropdown-toggle">
            <div class="p24">Формат</div>
            <div class="filter-icon w-icon-dropdown-toggle"></div>
          </div>
          <nav class="dropdown-list w-dropdown-list">
            <a href="#" class="p24 dropdown-list-item w-dropdown-link">Link 1</a>
            <a href="#" class="p24 dropdown-list-item w-dropdown-link">Link 1</a>
            <a href="#" class="p24 dropdown-list-item w-dropdown-link">Link 1</a>
          </nav>
        </div>
        <div data-hover="false" data-delay="0" class="filter w-dropdown">
          <div class="filter-select w-dropdown-toggle">
            <div class="p24">Цель</div>
            <div class="filter-icon w-icon-dropdown-toggle"></div>
          </div>
          <nav class="dropdown-list w-dropdown-list">
            <a href="#" class="p24 dropdown-list-item w-dropdown-link">Link 1</a>
            <a href="#" class="p24 dropdown-list-item w-dropdown-link">Link 2</a>
            <a href="#" class="p24 dropdown-list-item w-dropdown-link">Link 3</a>
          </nav>
        </div>
        <div data-hover="false" data-delay="0" class="filter selected w-dropdown">
          <div class="filter-select w-dropdown-toggle">
            <div class="filter-conter">2</div>
            <div class="p24 white-text">Предметы</div>
            <div class="filter-icon white-text w-icon-dropdown-toggle"></div>
          </div>
          <nav class="dropdown-list w-dropdown-list">
            <a href="#" class="p24 dropdown-list-item w-dropdown-link">Link 1</a>
            <a href="#" class="p24 dropdown-list-item w-dropdown-link">Link 1</a>
            <a href="#" class="p24 dropdown-list-item w-dropdown-link">Link 1</a>
          </nav>
        </div>
        <div data-hover="false" data-delay="0" class="filter w-dropdown">
          <div class="filter-select w-dropdown-toggle">
            <div class="p24">Классы</div>
            <div class="filter-icon w-icon-dropdown-toggle"></div>
          </div>
          <nav class="dropdown-list w-dropdown-list">
            <a href="#" class="p24 dropdown-list-item w-dropdown-link">Link 1</a>
            <a href="#" class="p24 dropdown-list-item w-dropdown-link">Link 1</a>
            <a href="#" class="p24 dropdown-list-item w-dropdown-link">Link 1</a>
          </nav>
        </div>
      </div>
    </div>
    <div class="specialists-list">
      @foreach(App\Models\User::all() as $user) 
        <a href="{{ route('tutors.show', $user) }}" class="specialist-list-item w-inline-block">
          <div class="specialist-list-item-group"><img src="images/Rectangle-9.png" loading="lazy" width="112" height="112" alt="" class="list-item-userpic">
            <div class="specialist-list-item-details">
              <div class="list-item-name-tags">
                <div class="p30">{{ $user->name }}</div>
                <div class="direction-tags-list">
                  @foreach($user->directs as $direct)
                    <div class="direction-tag">
                      <div class="p18">{{ $direct->name }}</div>
                    </div> 
                  @endforeach
                </div>
              </div>
              <div class="list-item-subject-grade">
                <div class="p24">{{ $user->subjectsList }}</div>
                <div class="p18">{{ $user->grade }}</div>
              </div>
            </div>
          </div>
        </a>
      @endforeach
    </div>
  </section> 
  <div class="popup-wrapper auto-stopper">
    <div class="popup">
      <div style="padding-top:56.27659574468085%" class="video w-video w-embed"><iframe class="embedly-embed" src="https://cdn.embedly.com/widgets/media.html?src=https%3A%2F%2Fplayer.vimeo.com%2Fvideo%2F1007718657%3Fapp_id%3D122963&dntp=1&display_name=Vimeo&url=https%3A%2F%2Fvimeo.com%2F1007718657&image=https%3A%2F%2Fi.vimeocdn.com%2Fvideo%2F1924634575-d0cb03e50ef9708d7c52b0af022f099fa4593fc366fe3db58eadc9ac85e2ff8d-d_1280&key=c4e54deccf4d4ec997a64902e9a30300&type=text%2Fhtml&schema=vimeo" width="940" height="529" scrolling="no" allowfullscreen="" title="The Game"></iframe></div>
    </div>
    <div class="close-button">Закрыть</div>
    <div data-w-id="43e43230-fe7b-3950-1162-acc7201c2860" class="close-click-zone"></div>
  </div>
@endsection