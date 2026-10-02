{{-- Статьи блога на других страницах сайта (главная, страница учителя): заголовок, ссылка на все и сетка в 4 колонки.
     Параметры: posts, heading, id (якорь заголовка), allUrl, allLabel. Стили — blog.css (подключает страница). --}}
<section class="content tutor-blog" aria-labelledby="{{ $id }}">
  <div class="tutor-blog-head">
    <h2 id="{{ $id }}" class="tutor-blog-title">{{ $heading }}</h2>
    @if($allUrl)
      <a href="{{ $allUrl }}" class="tutor-blog-all">{{ $allLabel ?? 'Все статьи' }} →</a>
    @endif
  </div>
  <div class="blog-grid">
    @php($plain = 0)
    @foreach($posts as $post)
      @include('blog.partials.card', ['post' => $post, 'titleTag' => 'h3', 'style' => ['fill', 'outline', 'mint'][$plain % 3]])
      @php($plain += $post->cover_url ? 0 : 1)
    @endforeach
  </div>
</section>
