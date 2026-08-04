<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Http\Request;

class HelpController extends Controller
{
    /**
     * Главная страница базы знаний: поиск + карточки разделов
     */
    public function index(Request $request)
    {
        $query = trim((string) $request->input('q', ''));

        $results = null;
        if ($query !== '') {
            $results = HelpArticle::search($query)
                ->with('category')
                ->orderBy('sort_order')
                ->take(50)
                ->get();
        }

        $stats = HelpCategory::published()
            ->withCount(['articles as published_articles_count' => fn($q) => $q->where('is_published', true)])
            ->get()
            ->groupBy('audience')
            ->map(fn($categories) => [
                'categories' => $categories->count(),
                'articles' => $categories->sum('published_articles_count'),
            ]);

        return view('help.index', [
            'query' => $query,
            'results' => $results,
            'stats' => $stats,
        ]);
    }

    /**
     * Страница раздела («Для учеников» / «Для репетиторов»): сетка категорий
     */
    public function section(string $audienceSlug)
    {
        $audience = HelpCategory::audienceFromSlug($audienceSlug);

        if ($audience === null) {
            // Старые ссылки вида /help/{category} — редиректим на новый URL
            $category = HelpCategory::published()->where('slug', $audienceSlug)->first();
            if ($category) {
                return redirect()->route('help.category', [$category->audience_slug, $category->slug], 301);
            }
            abort(404);
        }

        $categories = HelpCategory::published()
            ->where('audience', $audience)
            ->with('publishedArticles')
            ->orderBy('sort_order')
            ->get();

        return view('help.section', [
            'audience' => $audience,
            'audienceSlug' => $audienceSlug,
            'audienceLabel' => HelpCategory::AUDIENCES[$audience],
            'categories' => $categories,
        ]);
    }

    /**
     * Страница категории: список статей + сайдбар навигации
     */
    public function category(string $audienceSlug, string $categorySlug)
    {
        [$category, $sidebarCategories] = $this->resolveCategory($audienceSlug, $categorySlug);

        $articles = $sidebarCategories->firstWhere('id', $category->id)->publishedArticles;

        return view('help.category', compact('category', 'articles', 'sidebarCategories'));
    }

    /**
     * Страница статьи + сайдбар навигации и ссылка на следующую статью
     */
    public function article(string $audienceSlug, string $categorySlug, string $articleSlug)
    {
        [$category, $sidebarCategories] = $this->resolveCategory($audienceSlug, $categorySlug);

        $article = $category->publishedArticles()
            ->where('slug', $articleSlug)
            ->firstOrFail();

        // Уникальные просмотры: считаем один раз за сессию посетителя
        $viewedKey = 'help_viewed_articles';
        $viewed = session($viewedKey, []);
        if (!in_array($article->id, $viewed, true)) {
            $article->increment('views_count');
            $viewed[] = $article->id;
            session([$viewedKey => $viewed]);
        }

        $categoryArticles = $sidebarCategories->firstWhere('id', $category->id)->publishedArticles;
        $currentIndex = $categoryArticles->search(fn($a) => $a->id === $article->id);
        $nextArticle = $currentIndex !== false ? $categoryArticles->get($currentIndex + 1) : null;

        return view('help.article', compact('category', 'article', 'sidebarCategories', 'nextArticle'));
    }

    /**
     * Находит опубликованную категорию в разделе + категории раздела для сайдбара
     *
     * @return array{0: HelpCategory, 1: \Illuminate\Database\Eloquent\Collection}
     */
    private function resolveCategory(string $audienceSlug, string $categorySlug): array
    {
        $audience = HelpCategory::audienceFromSlug($audienceSlug) ?? abort(404);

        $sidebarCategories = HelpCategory::published()
            ->where('audience', $audience)
            ->with('publishedArticles')
            ->orderBy('sort_order')
            ->get();

        $category = $sidebarCategories->firstWhere('slug', $categorySlug) ?? abort(404);

        return [$category, $sidebarCategories];
    }
}
