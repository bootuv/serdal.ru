@extends('layout')

@section('content')
  <section class="header">
    <a href="/" class="logo-wrapper w-inline-block"><img src="images/Logo.svg" loading="lazy" width="Auto" height="32" alt="" class="logo"></a>
    <div class="menu-wrapper">
      <div class="main-menu">
        <a href="#" target="_blank" class="p24">О нас</a>
        <a href="{{ route('reviews') }}" class="p24">Отзывы</a>
        <a href="https://room.serdal.ru/signin" target="_blank" class="p24">Войти</a>
      </div>
      <div data-w-id="a8ac7203-c22a-a2cb-1d14-2d129698914f" class="burger-menu-wrapper"><img src="images/burger.svg" loading="lazy" width="32" height="32" alt="" class="burger-menu"></div>
    </div>
  </section>
  <section class="profile">
    <div class="profile-pic-wrapper"><img src="images/Rectangle-4_1.png" loading="lazy" width="280" height="280" alt="" srcset="images/Rectangle-4_1-p-500.png 500w, images/Rectangle-4_1.png 560w" sizes="280px" class="profile-pic"></div>
    <h2 class="h3 tutor-name">{{ $user->name }}</h2>
    <div class="status">
      <div class="p24">{{ $user->status }}</div>
      <div class="status-arrow"></div>
    </div>
    <div class="tutor-subjects p24">{{ $user->subjects_list }}</div>
    <div class="direction-tags-list tutor-page">
      @foreach($user->directs as $direct)
        <div class="direction-tag tutor-page">
          <div class="p24">{{ $direct->name }}</div>
        </div>
      @endforeach
    </div>
    <div class="grades p24">{{ $user->displayGrade }}</div>
    <a href="#" class="main-button share-button w-inline-block">
      <img src="images/share-01.svg" loading="lazy" width="32" height="32" alt="">
      <div class="p24">Поделиться страницей</div>
    </a>
  </section>
  <section class="content">
    <div class="col-50 vertical">
      <div class="content-card">
        <h4 class="h4">Обо мне</h4>
        <p class="p24">{{ $user->about }}</p>
      </div>
      <div class="content-card">
        <h4 class="h4">Дополнительная информация</h4>
        <p class="p24">{{ $user->extra_info }}</p>
      </div>
    </div>
    <div class="col-50 horizontal">
      <div class="col-25">
        <div class="content-card">
          <h4 class="h4">Занятия</h4>
      
          <div class="group-classes">
            @if($lessonTypeGroup)
            <div class="class-info-title">
              <div class="p24">Групповые</div>
            </div>
            <div class="param-list">
              <div class="param-list-item">
                <div class="param-item-label p18">Цена</div>
                <div class="param-item-data">
                  <div class="price">
                    <div class="p24-medium">{{ $lessonTypeGroup->price }} ₽</div>
                    <div class="p18">/ в месяц</div>
                  </div>
                </div>
              </div>
              <div class="param-list-item">
                <div class="param-item-label p18">Занятий в неделю</div>
                <div class="param-item-data">
                  <div class="param-list-item-text">
                    <div class="p18-medium">{{ $lessonTypeGroup->count_per_week }}</div>
                  </div>
                </div>
              </div>
              <div class="param-list-item last">
                <div class="param-item-label p18">Длина занятия</div>
                <div class="param-item-data">
                  <div class="param-list-item-text">
                    <div class="p18-medium">{{ $lessonTypeGroup->duration }} минут</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          @endif
          @if($lessonTypeIndividual)
          <div class="individual-classes">
            <div class="class-info-title">
              <div class="p24">Индивидуальные</div>
            </div>
            <div class="param-list">
              <div class="param-list-item">
                <div class="param-item-label p18">Цена</div>
                <div class="param-item-data">
                  <div class="price">
                    <div class="p24-medium">{{ $lessonTypeIndividual->price }} ₽</div>
                    <div class="p18">/ за урок</div>
                  </div>
                </div>
              </div>
              <div class="param-list-item">
                <div class="param-item-label p18">Занятий в неделю</div>
                <div class="param-item-data">
                  <div class="param-list-item-text">
                    <div class="p18-medium">{{ $lessonTypeIndividual->count_per_week }}</div>
                  </div>
                </div>
              </div>
              <div class="param-list-item last">
                <div class="param-item-label p18">Длина занятия</div>
                <div class="param-item-data">
                  <div class="param-list-item-text">
                    <div class="p18-medium">{{ $lessonTypeIndividual->duration }} минут</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          @endif
        </div>
      </div>
      <div class="col-25">
        <div class="content-card">
          <h4 class="h4">Способы связи</h4>
          <div class="contacts">
            <div class="param-list">
              <div class="param-list-item icon">
                <div class="param-item-label p18">Телефон</div>
                <div class="param-item-data">
                  <div class="price">
                    <a href="tel:{{ $user->phone }}" class="text-link w-inline-block">
                      <div class="p18-medium">{{ $user->phone }}</div>
                    </a>
                  </div>
                </div>
                <div class="w-layout-blockcontainer contact-icon w-container"><img src="images/Phone.svg" loading="lazy" alt="" class="icon-svg"></div>
              </div>
              <div class="param-list-item icon">
                <div class="param-item-label p18">WhatsApp</div>
                <div class="param-item-data">
                  <div class="param-list-item-text">
                    <a href="https://wa.me/{{ $user->whatsup }}" class="text-link w-inline-block">
                      <div class="p18-medium">{{ $user->whatsup }}</div>
                    </a>
                  </div>
                </div>
                <div class="w-layout-blockcontainer contact-icon w-container"><img src="images/WhatsApp.svg" loading="lazy" alt="" class="icon-svg"></div>
              </div>
              <div class="param-list-item icon">
                <div class="param-item-label p18">Telegram</div>
                <div class="param-item-data">
                  <div class="param-list-item-text">
                    <a href="https://t.me/{{ $user->telegram }}" class="text-link w-inline-block">
                      <div class="p18-medium">{{ "@" . $user->telegram }}</div>
                    </a>
                  </div>
                </div>
                <div class="w-layout-blockcontainer contact-icon w-container"><img src="images/Telegram.svg" loading="lazy" alt="" class="icon-svg"></div>
              </div>
              <div class="param-list-item icon">
                <div class="param-item-label p18">Instagram</div>
                <div class="param-item-data">
                  <div class="param-list-item-text">
                    <a href="https://www.instagram.com/{{ $user->instagram }}" class="text-link w-inline-block">
                      <div class="p18-medium">{{ "@" . $user->instagram }}</div>
                    </a>
                  </div>
                </div>
                <div class="w-layout-blockcontainer contact-icon w-container"><img src="images/Instagram.svg" loading="lazy" alt="" class="icon-svg"></div>
              </div>
              <div class="param-list-item icon last">
                <div class="param-item-label p18">Email</div>
                <div class="param-item-data">
                  <div class="param-list-item-text">
                    <a href="mailto:{{ $user->email }}" class="text-link w-inline-block">
                      <div class="p18-medium">{{ $user->email }}</div>
                    </a>
                  </div>
                </div>
                <div class="w-layout-blockcontainer contact-icon w-container"><img src="images/Email.svg" loading="lazy" alt="" class="icon-svg"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection