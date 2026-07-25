<div class="content-card help-category-card">
    <a href="{{ $category->url }}" class="p24-medium help-category-title">
        {{ $category->icon ? $category->icon . ' ' : '' }}{{ $category->name }}
    </a>
    @if($category->publishedArticles->isNotEmpty())
        <div class="help-category-articles">
            @foreach($category->publishedArticles->take(3) as $article)
                <a href="{{ $article->url }}" class="p18">{{ $article->title }}</a>
            @endforeach
        </div>
    @elseif($category->description)
        <div class="p18 help-category-description" style="color: var(--gray); margin-bottom: 24px;">
            {{ $category->description }}
        </div>
    @endif
    <a href="{{ $category->url }}" class="p18 help-category-all">Все статьи →</a>
</div>
