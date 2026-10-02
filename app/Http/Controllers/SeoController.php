<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\Tariff;
use App\Models\User;
use App\Services\IndexNowService;
use App\Services\SitemapService;
use App\Services\SubscriptionService;
use App\Services\TutorCatalogService;
use App\Support\OfferSettings;
use App\Support\Seo;
use App\Support\SeoSettings;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Служебные файлы для поисковых систем и ИИ-краулеров:
 * robots.txt, sitemap.xml, llms.txt и llms-full.txt.
 */
class SeoController extends Controller
{
    private const CACHE_TTL = 3600;

    /** Пути, закрытые от индексации (личный кабинет, платежи, комнаты занятий). */
    private const DISALLOW = [
        '/admin',
        '/cabinet',
        '/login',
        '/forgot-password',
        '/reset-password/',
        '/welcome',
        '/register/',
        '/r/',
        '/recordings/',
        '/rooms/',
        '/session/',
        '/subscription/',
        '/payments/',
        '/google/',
        '/push-subscription',
        '/reviews/load-more',
        '/livewire/',
        '/*?offset=',
    ];

    /** Параметры адреса, которые не меняют содержимое страницы (директива Clean-param для Яндекса). */
    private const CLEAN_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'yclid', 'gclid', 'fbclid', 'ysclid', 'from', 'ref', 'sort', 'offset',
    ];

    /** ИИ-краулеры, которым явно разрешён доступ к публичным страницам. */
    private const AI_BOTS = [
        'GPTBot',
        'OAI-SearchBot',
        'ChatGPT-User',
        'ClaudeBot',
        'Claude-User',
        'Claude-SearchBot',
        'anthropic-ai',
        'PerplexityBot',
        'Perplexity-User',
        'Google-Extended',
        'Applebot-Extended',
        'CCBot',
        'YandexAdditional',
        'Bingbot',
        'DuckAssistBot',
        'MistralAI-User',
        'Meta-ExternalAgent',
        'Amazonbot',
    ];

    /** Краулеры, собирающие данные для обучения моделей: закрываются переключателем в админке. */
    private const AI_TRAINING_BOTS = [
        'GPTBot',
        'ClaudeBot',
        'anthropic-ai',
        'Google-Extended',
        'Applebot-Extended',
        'CCBot',
        'Meta-ExternalAgent',
        'Amazonbot',
        'Bytespider',
    ];

    public function robots(): Response
    {
        // Индексация выключена в админке (например, на тестовом стенде) — закрываем всё
        if (!SeoSettings::enabled('seo_indexing_enabled')) {
            return $this->text("User-agent: *\nDisallow: /\n");
        }

        $aiAllowed = SeoSettings::enabled('seo_ai_crawlers_enabled');

        $lines = ['User-agent: *', 'Allow: /'];
        foreach (self::DISALLOW as $path) {
            $lines[] = 'Disallow: ' . $path;
        }
        $lines[] = '';

        // Яндекс: метки рекламы и фильтры каталога не создают новых страниц (остальные поисковики смотрят на canonical)
        $lines[] = 'User-agent: Yandex';
        $lines[] = 'Allow: /';
        foreach (self::DISALLOW as $path) {
            $lines[] = 'Disallow: ' . $path;
        }
        $lines[] = 'Clean-param: ' . implode('&', self::CLEAN_PARAMS);
        $lines[] = '';

        if ($aiAllowed) {
            $lines[] = '# ИИ-ассистентам и поисковым краулерам доступ к публичным страницам разрешён';
            foreach (self::AI_BOTS as $bot) {
                $lines[] = 'User-agent: ' . $bot;
                $lines[] = 'Allow: /';
                foreach (self::DISALLOW as $path) {
                    $lines[] = 'Disallow: ' . $path;
                }
                $lines[] = '';
            }
        } else {
            $lines[] = '# Сбор материалов сайта для обучения ИИ-моделей запрещён (настройка в админке)';
            foreach (self::AI_TRAINING_BOTS as $bot) {
                $lines[] = 'User-agent: ' . $bot;
                $lines[] = 'Disallow: /';
                $lines[] = '';
            }
        }

        $lines[] = 'Sitemap: ' . Seo::url('sitemap.xml');
        if ($aiAllowed) {
            $lines[] = '# Краткое описание сайта для ИИ: ' . Seo::url('llms.txt');
        }

        return $this->text(implode("\n", $lines) . "\n");
    }

    public function sitemap(): Response
    {
        $xml = Cache::remember('seo.sitemap', self::CACHE_TTL, function () {
            return view('seo.sitemap', ['urls' => app(SitemapService::class)->urls()])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** Ключ IndexNow: поисковик скачивает его, чтобы убедиться, что адреса прислал владелец сайта. */
    public function indexNowKey(): Response
    {
        return $this->text(IndexNowService::key());
    }

    public function llms(): Response
    {
        $text = Cache::remember('seo.llms', self::CACHE_TTL, fn () => $this->buildLlms(false));

        return $this->text($text);
    }

    public function llmsFull(): Response
    {
        $text = Cache::remember('seo.llms-full', self::CACHE_TTL, fn () => $this->buildLlms(true));

        return $this->text($text);
    }

    private function publicTutors()
    {
        return app(TutorCatalogService::class)->publicTutorsQuery()
            ->with(['subjects', 'directs'])
            ->orderBy('id')
            ->get();
    }

    private function buildLlms(bool $full): string
    {
        $offer = OfferSettings::offer();
        $legal = OfferSettings::legal();
        $b2b = OfferSettings::b2b();
        $name = Seo::siteName();

        $out = [];
        $out[] = '# ' . $name;
        $out[] = '';
        $out[] = '> ' . Seo::text(SeoSettings::get('seo_llms_description'), 1000);
        $out[] = '';
        $out[] = 'Сайт: ' . Seo::url('/');
        $out[] = 'Язык: русский. Аудитория: репетиторы, менторы, образовательные центры, ученики и родители.';
        $out[] = 'Контакт: ' . $legal['legal_email'];
        $out[] = '';

        $out[] = '## Основные страницы';
        $out[] = '- [Главная и каталог репетиторов](' . Seo::url('/') . '): поиск преподавателя по направлениям, предметам и классам';
        $out[] = '- [О платформе](' . Seo::url(route('about', [], false)) . '): возможности для преподавателей и учеников';
        $out[] = '- [Тарифы](' . Seo::url(route('tariffs', [], false)) . '): подписка для репетиторов и образовательных центров, порядок оплаты и возврата';
        $out[] = '- [Отзывы](' . Seo::url(route('reviews', [], false)) . '): отзывы учеников о преподавателях';
        $out[] = '- [Центр помощи](' . Seo::url(route('help.index', [], false)) . '): инструкции и видео для учеников и репетиторов';
        $out[] = '- [Блог](' . Seo::url(route('blog.index', [], false)) . '): статьи об образовании, подготовке к экзаменам и занятиях онлайн';
        $out[] = '- [Стать преподавателем](' . Seo::url(route('become-tutor', [], false)) . '): заявка на регистрацию репетитора';
        $out[] = '- [Репетиторы по предметам](' . Seo::url(route('catalog.index', [], false)) . '): подборки учителей по предметам и направлениям подготовки';
        $out[] = '';

        $catalog = app(TutorCatalogService::class)->catalog();
        $stats = $catalog['stats'];
        if ($stats['count'] > 0) {
            $out[] = '## Каталог репетиторов в цифрах';
            $out[] = '- Учителей с открытой страницей: ' . $stats['count'];
            if ($stats['price_min'] !== null) {
                $out[] = '- Цена занятия: от ' . $stats['price_min'] . ' до ' . $stats['price_max'] . ' ₽, в среднем ' . $stats['price_avg'] . ' ₽ (цену назначает учитель)';
            }
            if ($stats['rating'] !== null) {
                $out[] = '- Средняя оценка учителей: ' . number_format($stats['rating'], 1, ',', '') . ' из 5 по ' . $stats['reviews'] . ' отзывам учеников';
            }
            if ($stats['grades'] !== '') {
                $out[] = '- Ученики: ' . $stats['grades'];
            }
            $out[] = '- Для ученика платформа бесплатна: занятия оплачиваются учителю напрямую. Занятия идут в браузере — видеосвязь, интерактивная доска, демонстрация экрана, запись урока.';
            $out[] = '';

            $out[] = '## Репетиторы по предметам';
            foreach (collect($catalog['subjects'])->sortByDesc('count') as $page) {
                $out[] = '- [' . $page['heading'] . ' онлайн](' . $page['url'] . '): ' . plural_ru($page['count'], 'учитель', 'учителя', 'учителей');
            }
            $out[] = '';

            $out[] = '## Направления подготовки';
            foreach (collect($catalog['directs'])->sortByDesc('count') as $page) {
                $out[] = '- [' . $page['heading'] . ' онлайн](' . $page['url'] . '): ' . plural_ru($page['count'], 'учитель', 'учителя', 'учителей');
            }
            foreach (collect($catalog['combos'])->where('indexable', true)->sortByDesc('count') as $page) {
                $out[] = '- [' . $page['heading'] . ' онлайн](' . $page['url'] . '): ' . plural_ru($page['count'], 'учитель', 'учителя', 'учителей');
            }
            $out[] = '';
        }

        $out[] = '## Тарифы для преподавателей';
        $tariffs = Tariff::active()->get();
        foreach ($tariffs as $tariff) {
            $price = $tariff->isFree() ? 'бесплатно' : number_format($tariff->price, 0, ',', ' ') . ' ₽/мес';
            $line = '- ' . $tariff->name . ' — ' . $price;
            if ($tariff->short_description) {
                $line .= ': ' . Seo::text($tariff->short_description, 200);
            }
            $out[] = $line;
            if ($full) {
                $details = array_filter([
                    $tariff->lessons_label,
                    $tariff->participants_label,
                    $tariff->duration_label,
                    $tariff->recording_label,
                ]);
                if ($details) {
                    $out[] = '  - ' . implode('; ', $details);
                }
                foreach ((array) $tariff->features as $feature) {
                    $out[] = '  - ' . Seo::text($feature, 200);
                }
            }
        }
        $out[] = '- Докупка занятий сверх лимита тарифа: ' . number_format(SubscriptionService::extraLessonPrice(), 0, ',', ' ') . ' ₽ за занятие';
        if ($b2b['enabled']) {
            $out[] = '- ' . $b2b['title'] . ' — ' . $b2b['price_label'] . '/мес: ' . Seo::text($b2b['description'], 200);
        }
        $out[] = '- Оплата: ' . $offer['payment_methods'] . ' через ' . $offer['payment_provider']
            . '. Возврат в течение ' . $offer['refund_days'] . ' дней, если сервис не использовался.';
        $out[] = '';

        $categories = HelpCategory::published()
            ->with(['publishedArticles' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        if ($categories->isNotEmpty()) {
            $out[] = '## Центр помощи';
            foreach ($categories->groupBy('audience') as $audience => $group) {
                $slug = HelpCategory::AUDIENCE_SLUGS[$audience] ?? $audience;
                $out[] = '### ' . (HelpCategory::AUDIENCES[$audience] ?? $audience)
                    . ' (' . Seo::url(route('help.section', $slug, false)) . ')';
                foreach ($group as $category) {
                    $out[] = '- [' . $category->name . '](' . Seo::url(route('help.category', [$category->audience_slug, $category->slug], false)) . ')'
                        . ($category->description ? ': ' . Seo::text($category->description, 200) : '');
                    foreach ($category->publishedArticles as $article) {
                        $articleUrl = Seo::url(route('help.article', [$category->audience_slug, $category->slug, $article->slug], false));
                        $out[] = '  - [' . $article->title . '](' . $articleUrl . ')'
                            . ($article->excerpt ? ': ' . Seo::text($article->excerpt, 200) : '');
                    }
                }
            }
            $out[] = '';
        }

        $posts = \App\Models\BlogPost::published()->with(['tags', 'author'])->latest('published_at')->limit(100)->get();
        if ($posts->isNotEmpty()) {
            $out[] = '## Блог';
            $out[] = 'Статьи репетиторов Serdal. Каждая статья есть в Markdown — тот же адрес с окончанием .md. Лента с полным текстом: ' . Seo::url(route('blog.rss', [], false));
            foreach ($posts as $post) {
                $out[] = '- [' . $post->title . '](' . Seo::url(route('blog.show', $post->slug, false)) . ') · [Markdown](' . Seo::url(route('blog.markdown', $post->slug, false)) . '): ' . $post->description(200);
            }
            $out[] = '';
            // Полная версия — с текстом статей, чтобы ИИ-ассистент мог ответить по ним без перехода на сайт
            if ($full) {
                $blog = app(\App\Services\BlogService::class);
                foreach ($posts->take(30) as $post) {
                    $out[] = '---';
                    $out[] = '';
                    $out[] = preg_replace('/^# /', '### ', $blog->markdown($post), 1);
                }
            }
        }

        $tutors = $this->publicTutors();
        if ($tutors->isNotEmpty()) {
            $out[] = '## Преподаватели на платформе';
            foreach ($tutors as $tutor) {
                $line = '- [' . \Illuminate\Support\Str::squish($tutor->name) . '](' . Seo::url(route('tutors.show', $tutor, false)) . ')';
                $parts = array_filter([
                    $tutor->subjectsList,
                    $tutor->directs->pluck('name')->implode(', '),
                    $tutor->displayGrade,
                ]);
                if ($parts) {
                    $line .= ': ' . implode('; ', $parts);
                }
                $out[] = $line;
            }
            $out[] = '';
        }

        $out[] = '## Документы';
        $out[] = '- [Политика конфиденциальности](' . Seo::url(route('privacy', [], false)) . ')';
        $out[] = '- [Условия использования](' . Seo::url(route('terms', [], false)) . ')';
        $out[] = '- [Публичная оферта](' . Seo::url(route('offer', [], false)) . ')';
        $out[] = '';

        if (!$full) {
            $out[] = '## Optional';
            $out[] = '- [Полная версия с текстами статей центра помощи](' . Seo::url('llms-full.txt') . ')';
            $out[] = '- [Карта сайта](' . Seo::url('sitemap.xml') . ')';
            $out[] = '';

            return implode("\n", $out);
        }

        // Полная версия: тексты статей центра помощи
        $articles = HelpArticle::published()
            ->whereHas('category', fn ($q) => $q->where('is_published', true))
            ->with('category')
            ->orderBy('help_category_id')
            ->orderBy('sort_order')
            ->get();

        if ($articles->isNotEmpty()) {
            $out[] = '## Статьи центра помощи';
            $out[] = '';
            foreach ($articles as $article) {
                $out[] = '### ' . $article->title;
                $out[] = 'URL: ' . Seo::url(route('help.article', [$article->category->audience_slug, $article->category->slug, $article->slug], false));
                $out[] = 'Раздел: ' . $article->category->audience_label . ' / ' . $article->category->name;
                if ($article->has_video) {
                    $out[] = 'Формат: статья с видеоинструкцией';
                }
                $out[] = '';
                $text = Seo::text($article->content, 20000);
                if ($text !== '') {
                    $out[] = $text;
                    $out[] = '';
                }
            }
        }

        return implode("\n", $out);
    }

    private function text(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
