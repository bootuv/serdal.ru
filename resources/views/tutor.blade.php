@extends('layout')

@section('title')
  {{ $user->name }} - Преподаватель Serdal
@endsection

@section('meta')
  <meta property="og:title" content="{{ $user->name }} - Репетитор Serdal">
  <meta property="og:description" content="{{ $user->subjects_list }} - {{ $user->status }}">
  <meta property="og:image" content="{{ $user->avatarUrl }}">
  <meta property="og:type" content="profile">
@endsection

@section('content')

  <section class="profile">
    <div class="profile-pic-wrapper">
      <img src="{{ $user->avatarUrl }}" loading="lazy" width="280" height="280" alt="" sizes="280px" class="profile-pic">
    </div>
    <h2 class="h3 tutor-name">{{ $user->name }}</h2>
    @if(!empty($user->status) && trim(strip_tags($user->status)) !== '')
      <div class="status">
        <div class="status-arrow"></div>
        <div class="p24">{{ $user->status }}</div>
      </div>
    @endif
    @if(!empty($user->subjects_list) && trim(strip_tags($user->subjects_list)) !== '')
      <div class="tutor-subjects p24">{{ $user->subjects_list }}</div>
    @endif
    @if($user->directs && $user->directs->count() > 0)
      <div class="direction-tags-list tutor-page">
        @foreach($user->directs as $direct)
          <div class="direction-tag tutor-page">
            <div class="p24">{{ $direct->name }}</div>
          </div>
        @endforeach
      </div>
    @endif
    @if(!empty($user->displayGrade) && trim(strip_tags($user->displayGrade)) !== '')
      <div class="grades p24">{{ $user->displayGrade }}</div>
    @endif
    <a href="javascript:void(0)" onclick="shareProfile()" class="main-button share-button w-inline-block">
      <img src="images/share-01.svg" loading="lazy" width="32" height="32" alt="">
      <div class="p24">Поделиться страницей</div>
    </a>
    <script>
      function shareProfile() {
        const shareData = {
          title: '{{ $user->name }} - Преподаватель Serdal',
          url: window.location.href
        };

        if (navigator.share) {
          navigator.share(shareData)
            .catch((error) => console.log('Error sharing:', error));
        } else {
          navigator.clipboard.writeText(window.location.href)
            .then(() => alert('Ссылка скопирована в буфер обмена'))
            .catch(() => alert('Не удалось скопировать ссылку'));
        }
      }
    </script>
  </section>
  <section class="content">
    <div class="col-50 vertical">
      @if($user->about)
        <div class="content-card">
          <h4 class="h4">Обо мне</h4>
          {!! $user->about !!}
        </div>
      @endif
      @if($user->extra_info)
        <div class="content-card">
          <h4 class="h4">Дополнительная информация</h4>
          {!! $user->extra_info !!}
        </div>
      @endif
    </div>
    <div class="col-50 horizontal">
      <div class="col-25">
        @if($lessonTypeGroup || $lessonTypeIndividual)
          <div class="content-card">
            <h4 class="h4">Занятия</h4>
            @if($lessonTypeGroup)
              <div class="group-classes">
                <div class="class-info-title">
                  <div class="p24">Групповые</div>
                </div>
                <div class="param-list">
                  @if($lessonTypeGroup->price)
                    <div class="param-list-item">
                      <div class="param-item-label p18">Цена</div>
                      <div class="param-item-data">
                        <div class="price">
                          <div class="p24-medium">{{ $lessonTypeGroup->price }} ₽</div>
                          <div class="p18">{{ $lessonTypeGroup->payment_type === 'monthly' ? '/ в месяц' : '/ за урок' }}</div>
                        </div>
                      </div>
                    </div>
                  @endif
                  @if($lessonTypeGroup->payment_type === 'monthly' && $lessonTypeGroup->count_per_week)
                    <div class="param-list-item">
                      <div class="param-item-label p18">Занятий в неделю</div>
                      <div class="param-item-data">
                        <div class="param-list-item-text">
                          <div class="p18-medium">{{ $lessonTypeGroup->count_per_week }}</div>
                        </div>
                      </div>
                    </div>
                  @endif
                  @if($lessonTypeGroup->duration)
                    <div class="param-list-item last">
                      <div class="param-item-label p18">Длина занятия</div>
                      <div class="param-item-data">
                        <div class="param-list-item-text">
                          <div class="p18-medium">{{ $lessonTypeGroup->duration }} минут</div>
                        </div>
                      </div>
                    </div>
                  @endif
                </div>
              </div>
            @endif
            @if($lessonTypeIndividual)
              <div class="individual-classes">
                <div class="class-info-title">
                  <div class="p24">Индивидуальные</div>
                </div>
                <div class="param-list">
                  @if($lessonTypeIndividual->price)
                    <div class="param-list-item">
                      <div class="param-item-label p18">Цена</div>
                      <div class="param-item-data">
                        <div class="price">
                          <div class="p24-medium">{{ $lessonTypeIndividual->price }} ₽</div>
                          <div class="p18">{{ $lessonTypeIndividual->payment_type === 'monthly' ? '/ в месяц' : '/ за урок' }}</div>
                        </div>
                      </div>
                    </div>
                  @endif
                  @if($lessonTypeIndividual->payment_type === 'monthly' && $lessonTypeIndividual->count_per_week)
                    <div class="param-list-item">
                      <div class="param-item-label p18">Занятий в неделю</div>
                      <div class="param-item-data">
                        <div class="param-list-item-text">
                          <div class="p18-medium">{{ $lessonTypeIndividual->count_per_week }}</div>
                        </div>
                      </div>
                    </div>
                  @endif
                  @if($lessonTypeIndividual->duration)
                    <div class="param-list-item last">
                      <div class="param-item-label p18">Длина занятия</div>
                      <div class="param-item-data">
                        <div class="param-list-item-text">
                          <div class="p18-medium">{{ $lessonTypeIndividual->duration }} минут</div>
                        </div>
                      </div>
                    </div>
                  @endif
                </div>
              </div>
            @endif
          </div>
        @endif
      </div>
      <div class="col-25">
        @if($user->phone || $user->whatsup || $user->telegram || $user->instagram || $user->email)
          <div class="content-card">
            <h4 class="h4">Способы связи</h4>
            <div class="contacts">
              <div class="param-list">
                @if($user->phone)
                  <div class="param-list-item icon">
                    <div class="param-item-label p18">Телефон</div>
                    <div class="param-item-data">
                      <div class="price">
                        <a href="tel:{{ $user->phone }}" class="text-link w-inline-block">
                          <div class="p18-medium">{{ $user->phone }}</div>
                        </a>
                      </div>
                    </div>
                    <div class="w-layout-blockcontainer contact-icon w-container"><img src="images/Phone.svg" loading="lazy"
                        alt="" class="icon-svg"></div>
                  </div>
                @endif
                @if($user->whatsup)
                  <div class="param-list-item icon">
                    <div class="param-item-label p18">WhatsApp</div>
                    <div class="param-item-data">
                      <div class="param-list-item-text">
                        <a href="https://wa.me/{{ $user->whatsup }}" class="text-link w-inline-block">
                          <div class="p18-medium">{{ $user->whatsup }}</div>
                        </a>
                      </div>
                    </div>
                    <div class="w-layout-blockcontainer contact-icon w-container"><img src="images/WhatsApp.svg"
                        loading="lazy" alt="" class="icon-svg"></div>
                  </div>
                @endif
                @if($user->telegram)
                  <div class="param-list-item icon">
                    <div class="param-item-label p18">Telegram</div>
                    <div class="param-item-data">
                      <div class="param-list-item-text">
                        <a href="https://t.me/{{ $user->telegram }}" class="text-link w-inline-block">
                          <div class="p18-medium">{{ "@" . $user->telegram }}</div>
                        </a>
                      </div>
                    </div>
                    <div class="w-layout-blockcontainer contact-icon w-container"><img src="images/Telegram.svg"
                        loading="lazy" alt="" class="icon-svg"></div>
                  </div>
                @endif
                @if($user->instagram)
                  <div class="param-list-item icon">
                    <div class="param-item-label p18">Instagram</div>
                    <div class="param-item-data">
                      <div class="param-list-item-text">
                        <a href="https://www.instagram.com/{{ $user->instagram }}" class="text-link w-inline-block">
                          <div class="p18-medium">{{ "@" . $user->instagram }}</div>
                        </a>
                      </div>
                    </div>
                    <div class="w-layout-blockcontainer contact-icon w-container"><img src="images/Instagram.svg"
                        loading="lazy" alt="" class="icon-svg"></div>
                  </div>
                @endif
                @if($user->email)
                  <div class="param-list-item icon last">
                    <div class="param-item-label p18">Email</div>
                    <div class="param-item-data">
                      <div class="param-list-item-text">
                        <a href="mailto:{{ $user->email }}" class="text-link w-inline-block">
                          <div class="p18-medium">{{ $user->email }}</div>
                        </a>
                      </div>
                    </div>
                    <div class="w-layout-blockcontainer contact-icon w-container"><img src="images/Email.svg" loading="lazy"
                        alt="" class="icon-svg"></div>
                  </div>
                @endif
              </div>
            </div>
          </div>
        @endif
      </div>
    </div>
  </section>
@endsection