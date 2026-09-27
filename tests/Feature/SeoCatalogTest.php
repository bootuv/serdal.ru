<?php

namespace Tests\Feature;

use App\Models\Direct;
use App\Models\LessonType;
use App\Models\Review;
use App\Models\Subject;
use App\Models\User;
use App\Services\IndexNowService;
use App\Services\TutorCatalogService;
use App\Support\SeoPhrases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeoCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Subject $math;

    private Subject $physics;

    private Direct $ege;

    protected function setUp(): void
    {
        parent::setUp();

        $this->math = Subject::create(['name' => 'Математика']);
        $this->physics = Subject::create(['name' => 'Физика']);
        $this->ege = Direct::create(['name' => 'ЕГЭ']);
    }

    private function tutor(string $username, array $subjects, array $directs = [], int $price = 1000): User
    {
        $tutor = User::factory()->create([
            'role' => User::ROLE_TUTOR,
            'username' => $username,
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
            'grade' => [9, 10, 11],
        ]);
        $tutor->subjects()->sync(collect($subjects)->pluck('id'));
        $tutor->directs()->sync(collect($directs)->pluck('id'));

        LessonType::create([
            'user_id' => $tutor->id,
            'type' => LessonType::TYPE_INDIVIDUAL,
            'payment_type' => 'per_lesson',
            'price' => $price,
            'duration' => 60,
        ]);

        return $tutor;
    }

    public function test_phrases_build_russian_headings(): void
    {
        $this->assertSame('Репетитор по математике', SeoPhrases::subjectHeading('Математика'));
        $this->assertSame('Репетитор по английскому языку', SeoPhrases::subjectHeading('Английский язык'));
        $this->assertSame('Репетитор по обществознанию', SeoPhrases::subjectHeading('Обществознание'));
        $this->assertSame('Репетитор по химии', SeoPhrases::subjectHeading('Химия'));
        $this->assertSame('Репетитор начальных классов', SeoPhrases::subjectHeading('Начальные классы'));
        $this->assertSame('Репетитор по предмету «Шахматы и go»', SeoPhrases::subjectHeading('Шахматы и go'));
        $this->assertSame('Подготовка к ЕГЭ по физике', SeoPhrases::comboHeading('Физика', 'ЕГЭ'));
        $this->assertSame('Репетитор по английскому языку с нуля', SeoPhrases::comboHeading('Английский язык', 'Язык с нуля'));
        $this->assertFalse(SeoPhrases::combinable('Язык с нуля', 'Литература'));
        $this->assertFalse(SeoPhrases::combinable('IT-наставничество', 'Информатика'));
        $this->assertSame('russkiy-yazyk', SeoPhrases::slug('Русский язык'));
    }

    public function test_subject_page_lists_tutors_with_prices_faq_and_structured_data(): void
    {
        $this->tutor('math-one', [$this->math], [$this->ege], 800);
        $this->tutor('math-two', [$this->math], [], 1200);
        $this->tutor('physics-one', [$this->physics], [$this->ege]);

        $this->get('/repetitory/matematika')
            ->assertOk()
            ->assertSee('<title>Репетитор по математике онлайн — 2 репетитора, от 800 ₽ за занятие | Serdal</title>', false)
            ->assertSee('Репетитор по математике онлайн</h1>', false)
            ->assertSee('/math-one', false)
            ->assertSee('/math-two', false)
            ->assertDontSee('/physics-one', false)
            ->assertSee('Частые вопросы')
            ->assertSee('от 800 до 1 200 ₽')
            ->assertSee('"@type":"CollectionPage"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"lowPrice":800', false)
            ->assertSee('<link rel="canonical" href="http://localhost/repetitory/matematika">', false)
            ->assertSee('http://localhost/repetitory/fizika', false);
    }

    public function test_combo_with_one_tutor_is_closed_from_index_and_sitemap(): void
    {
        $this->tutor('math-one', [$this->math], [$this->ege]);
        $this->tutor('physics-one', [$this->physics], [$this->ege]);
        $this->tutor('physics-two', [$this->physics], [$this->ege]);

        $this->get('/repetitory/matematika/ege')
            ->assertOk()
            ->assertSee('Подготовка к ЕГЭ по математике онлайн</h1>', false)
            ->assertSee('<meta name="robots" content="noindex, follow">', false);

        $this->get('/repetitory/fizika/ege')
            ->assertOk()
            ->assertSee('<meta name="robots" content="index, follow', false);

        $this->get('/sitemap.xml')
            ->assertSee('<loc>http://localhost/repetitory/fizika/ege</loc>', false)
            ->assertDontSee('<loc>http://localhost/repetitory/matematika/ege</loc>', false)
            ->assertSee('<loc>http://localhost/repetitory/matematika</loc>', false)
            ->assertSee('<loc>http://localhost/napravleniya/ege</loc>', false);
    }

    public function test_pages_without_tutors_do_not_exist(): void
    {
        $this->tutor('math-one', [$this->math]);

        $this->get('/repetitory/fizika')->assertNotFound();
        $this->get('/napravleniya/ege')->assertNotFound();
        $this->get('/repetitory/matematika/ege')->assertNotFound();
        $this->get('/repetitory/nesushchestvuyushchiy')->assertNotFound();
    }

    public function test_hidden_tutors_are_not_counted(): void
    {
        $this->tutor('math-one', [$this->math]);
        $blocked = $this->tutor('math-blocked', [$this->math]);
        $blocked->update(['is_blocked' => true]);

        $this->get('/repetitory/matematika')
            ->assertOk()
            ->assertSee('1 репетитор')
            ->assertDontSee('/math-blocked', false);
    }

    public function test_hub_home_and_footer_link_to_catalog_pages(): void
    {
        $this->tutor('math-one', [$this->math], [$this->ege]);

        $this->get('/repetitory')
            ->assertOk()
            ->assertSee('Репетиторы онлайн по предметам</h1>', false)
            ->assertSee('http://localhost/repetitory/matematika', false)
            ->assertSee('http://localhost/napravleniya/ege', false);

        $this->get('/')
            ->assertOk()
            ->assertSee('Репетиторы по предметам')
            ->assertSee('http://localhost/napravleniya/ege', false)
            ->assertSee('"@type":"FAQPage"', false);

        $this->get('/about')->assertSee('http://localhost/repetitory/matematika', false);
    }

    public function test_tutor_page_links_to_catalog_and_has_rating_markup(): void
    {
        $tutor = $this->tutor('math-one', [$this->math], [$this->ege]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'username' => 'student-one']);
        Review::create(['user_id' => $student->id, 'teacher_id' => $tutor->id, 'rating' => 5, 'text' => 'Отличные занятия']);

        $this->get('/math-one')
            ->assertOk()
            ->assertSee('<a href="http://localhost/repetitory/matematika">Математика</a>', false)
            ->assertSee('href="http://localhost/napravleniya/ege"', false)
            ->assertSee('"@type":"AggregateRating"', false)
            ->assertSee('"ratingValue":5', false)
            ->assertSee('"@type":"Offer"', false)
            ->assertSee('"item":"http://localhost/repetitory/matematika"', false);
    }

    public function test_trailing_slash_redirects_to_canonical_address(): void
    {
        // Тестовый клиент сам обрезает слэш в адресе, поэтому запрос собираем вручную
        $handle = fn (string $uri) => $this->app->make(\Illuminate\Contracts\Http\Kernel::class)
            ->handle(\Illuminate\Http\Request::create($uri));

        $response = $handle('http://localhost/about/');
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('http://localhost/about', $response->headers->get('Location'));

        $this->assertSame('http://localhost/about?utm_source=x', $handle('http://localhost/about/?utm_source=x')->headers->get('Location'));
        $this->assertSame(200, $handle('http://localhost/')->getStatusCode());
    }

    public function test_robots_cleans_params_for_yandex_and_closes_cabinet(): void
    {
        $this->get('/robots.txt')
            ->assertSee('User-agent: Yandex', false)
            ->assertSee('Clean-param: utm_source&', false)
            ->assertSee('Disallow: /cabinet', false);
    }

    public function test_llms_txt_lists_catalog_pages_with_numbers(): void
    {
        $this->tutor('math-one', [$this->math], [], 900);

        $this->get('/llms.txt')
            ->assertOk()
            ->assertSee('## Репетиторы по предметам', false)
            ->assertSee('[Репетитор по математике онлайн](http://localhost/repetitory/matematika): 1 учитель', false)
            ->assertSee('Цена занятия: от 900 до 900 ₽', false);
    }

    public function test_indexnow_key_file_and_submission(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $this->get('/indexnow.txt')->assertOk()->assertSeeText(IndexNowService::key());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', IndexNowService::key());

        Http::fake(['*' => Http::response('', 202)]);

        // Не на проде команда ничего не отправляет
        $this->artisan('seo:indexnow')->assertSuccessful();
        Http::assertNothingSent();

        $this->tutor('math-one', [$this->math]);

        $results = app(IndexNowService::class)->submit(app(IndexNowService::class)->changedUrls(all: true));

        $this->assertSame([202, 202], array_values($results));

        // Отправленное помнится и после очистки кэша (деплой делает optimize:clear); новые страницы уходят сразу
        Cache::flush();
        $this->assertSame([], app(IndexNowService::class)->changedUrls());
        $this->tutor('physics-one', [$this->physics]);
        Cache::forget(TutorCatalogService::CACHE_KEY);
        $this->assertContains('http://localhost/repetitory/fizika', app(IndexNowService::class)->changedUrls());
        Http::assertSent(fn ($request) => $request->url() === 'https://yandex.com/indexnow'
            && $request['key'] === IndexNowService::key()
            && in_array('http://localhost/repetitory/matematika', $request['urlList'], true));
    }

    public function test_merging_directions_in_admin_updates_catalog_at_once(): void
    {
        $opr = Direct::create(['name' => 'ОПР']);
        $vpr = Direct::create(['name' => 'ВПР']);
        $this->tutor('math-one', [$this->math], [$opr]);

        $this->get('/napravleniya/opr')->assertOk();

        app(\App\Services\DictionaryService::class)->merge('directs', $opr->id, $vpr->id);

        $this->get('/napravleniya/opr')->assertNotFound();
        $this->get('/napravleniya/vpr')->assertOk()->assertSee('Подготовка к ВПР онлайн</h1>', false);
    }

    public function test_catalog_is_cached_and_flushed_with_seo_settings(): void
    {
        $this->tutor('math-one', [$this->math]);
        app(TutorCatalogService::class)->catalog();
        $this->assertTrue(Cache::has(TutorCatalogService::CACHE_KEY));

        \App\Support\SeoSettings::flush();
        $this->assertFalse(Cache::has(TutorCatalogService::CACHE_KEY));
    }
}
