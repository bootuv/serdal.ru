{{-- Сайдбар навигации: $sidebarCategories, $currentCategory, $currentArticle (nullable) --}}
<aside class="help-sidebar" :class="{ 'open': navOpen }">
    <div class="help-sidebar-close" @click="navOpen = false">✕</div>
    <a href="{{ $currentCategory->audience_url }}" class="help-sidebar-back">← {{ $currentCategory->audience_label }}</a>
    @foreach($sidebarCategories as $sidebarCategory)
        <a href="{{ $sidebarCategory->url }}"
            class="help-sidebar-category {{ $sidebarCategory->id === $currentCategory->id ? 'active' : '' }}">
            {{ $sidebarCategory->icon ? $sidebarCategory->icon . ' ' : '' }}{{ $sidebarCategory->name }}
        </a>
        @if($sidebarCategory->id === $currentCategory->id && $sidebarCategory->publishedArticles->isNotEmpty())
            <div class="help-sidebar-articles">
                @foreach($sidebarCategory->publishedArticles as $sidebarArticle)
                    <a href="{{ $sidebarArticle->url }}"
                        class="help-sidebar-article {{ ($currentArticle?->id) === $sidebarArticle->id ? 'active' : '' }}">
                        {{ $sidebarArticle->title }}
                    </a>
                @endforeach
            </div>
        @endif
    @endforeach
</aside>
