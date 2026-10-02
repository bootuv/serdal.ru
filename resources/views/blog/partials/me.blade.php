{{-- Личный блок вошедшего в боковой колонке блога, как профиль в соцсетях: аватар, имя, счетчики, «Написать статью» и «Мои статьи и черновики» --}}
@php
    $me = auth()->user();
    if ($me) {
        $blogService = app(\App\Services\BlogService::class);
        $isTutor = $me->role === \App\Models\User::ROLE_TUTOR;
        $isAdmin = $me->role === \App\Models\User::ROLE_ADMIN;
        $followingCount = count($blogService->followedIds($me));
        // Счетчики: у учителя — статьи, подписчики, подписки; у ученика и админа — только подписки
        $stats = array_values(array_filter([
            $isTutor ? ['value' => $me->blogPosts()->published()->count(), 'label' => ['статья', 'статьи', 'статей'], 'href' => $me->username ? route('blog.author', $me->username) : null] : null,
            $isTutor ? ['value' => $blogService->followersCount($me), 'label' => ['подписчик', 'подписчика', 'подписчиков'], 'href' => null] : null,
            ['value' => $followingCount, 'label' => ['подписка', 'подписки', 'подписок'], 'href' => $followingCount ? route('blog.index', ['sort' => 'feed']) : null],
        ]));
        $writeUrl = match (true) {
            $isTutor && Route::has('cabinet.teacher.blog-article') => route('cabinet.teacher.blog-article', ['post' => 'new']),
            $isAdmin && Route::has('cabinet.admin.blog-article') => route('cabinet.admin.blog-article', ['post' => 'new']),
            default => null,
        };
        $draftsUrl = match (true) {
            $isTutor && Route::has('cabinet.teacher.blog') => route('cabinet.teacher.blog'),
            $isAdmin && Route::has('cabinet.admin.blog') => route('cabinet.admin.blog'),
            default => null,
        };
        $profileHref = $isTutor && $me->username ? route('blog.author', $me->username) : null;
    }
@endphp
@if($me)
    <section class="blog-me" aria-label="Ваш профиль в блоге">
        @if($profileHref)
            <a href="{{ $profileHref }}" class="blog-me-head">
                @include('partials.userpic', ['user' => $me, 'class' => 'blog-me-pic'])
                <span class="blog-me-name">{{ $me->name }}</span>
            </a>
        @else
            <div class="blog-me-head">
                @include('partials.userpic', ['user' => $me, 'class' => 'blog-me-pic'])
                <span class="blog-me-name">{{ $me->name }}</span>
            </div>
        @endif

        <div class="blog-me-stats">
            @foreach($stats as $stat)
                @if($stat['href'])
                    <a href="{{ $stat['href'] }}" class="blog-me-stat"><b>{{ $stat['value'] }}</b><span>{{ plural_ru($stat['value'], ...[...$stat['label'], false]) }}</span></a>
                @else
                    <div class="blog-me-stat"><b>{{ $stat['value'] }}</b><span>{{ plural_ru($stat['value'], ...[...$stat['label'], false]) }}</span></div>
                @endif
            @endforeach
        </div>

        @if($writeUrl)
            <div class="blog-me-actions">
                <a href="{{ $writeUrl }}" class="blog-button blog-me-write">Написать статью</a>
                @if($draftsUrl)<a href="{{ $draftsUrl }}" class="blog-button blog-button--outline blog-me-write">Мои статьи и черновики</a>@endif
            </div>
        @elseif(! $followingCount)
            <p class="blog-me-empty">Подпишитесь на авторов — их новые статьи будут в «Моей ленте».</p>
        @endif
    </section>
@endif
