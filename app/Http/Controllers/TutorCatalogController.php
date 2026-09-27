<?php

namespace App\Http\Controllers;

use App\Services\TutorCatalogService;
use App\Support\Seo;

/**
 * Посадочные страницы каталога репетиторов для поиска: по предмету, по направлению и их сочетанию.
 * Данные и тексты собирает TutorCatalogService.
 */
class TutorCatalogController extends Controller
{
    public function __construct(private TutorCatalogService $catalog)
    {
    }

    public function index()
    {
        $data = $this->catalog->catalog();
        $stats = $data['stats'];

        return view('catalog.index', [
            'subjects' => collect($data['subjects'])->sortByDesc('count')->values()->all(),
            'directs' => collect($data['directs'])->sortByDesc('count')->values()->all(),
            'stats' => $stats,
            'facts' => $this->catalog->factsLine($stats),
            'faq' => $this->catalog->faq($stats),
        ]);
    }

    public function subject(string $subject)
    {
        $page = $this->catalog->subject($subject) ?? abort(404);

        $related = collect($this->catalog->combosFor(subjectSlug: $subject))
            ->map(fn ($combo) => ['name' => $this->catalog->catalog()['directs'][$combo['direct']]['name'], 'url' => $combo['url'], 'count' => $combo['count']])
            ->all();

        return $this->landing($page['heading'], $this->catalog->tutors(subjectId: $page['id']), [
            ['name' => 'Репетиторы', 'url' => route('catalog.index')],
            ['name' => $page['name'], 'url' => $page['url']],
        ], [
            ['title' => 'Направления', 'links' => $related],
            ['title' => 'Другие предметы', 'links' => $this->otherSubjects($subject)],
        ]);
    }

    public function direct(string $direct)
    {
        $page = $this->catalog->direct($direct) ?? abort(404);
        $subjects = $this->catalog->catalog()['subjects'];

        $related = collect($this->catalog->combosFor(directSlug: $direct))
            ->map(fn ($combo) => ['name' => $subjects[$combo['subject']]['name'], 'url' => $combo['url'], 'count' => $combo['count']])
            ->all();

        $otherDirects = collect($this->catalog->catalog()['directs'])
            ->reject(fn ($item) => $item['slug'] === $direct)
            ->sortByDesc('count')
            ->map(fn ($item) => ['name' => $item['name'], 'url' => $item['url'], 'count' => $item['count']])
            ->values()
            ->all();

        return $this->landing($page['heading'], $this->catalog->tutors(directId: $page['id']), [
            ['name' => 'Репетиторы', 'url' => route('catalog.index')],
            ['name' => $page['name'], 'url' => $page['url']],
        ], [
            ['title' => 'Предметы', 'links' => $related],
            ['title' => 'Другие направления', 'links' => $otherDirects],
        ]);
    }

    public function combo(string $subject, string $direct)
    {
        $page = $this->catalog->combo($subject, $direct) ?? abort(404);
        $data = $this->catalog->catalog();
        $subjectPage = $data['subjects'][$subject];
        $directPage = $data['directs'][$direct];

        $siblings = collect($this->catalog->combosFor(subjectSlug: $subject))
            ->reject(fn ($combo) => $combo['direct'] === $direct)
            ->map(fn ($combo) => ['name' => $data['directs'][$combo['direct']]['name'], 'url' => $combo['url'], 'count' => $combo['count']])
            ->all();

        $sameDirect = collect($this->catalog->combosFor(directSlug: $direct))
            ->reject(fn ($combo) => $combo['subject'] === $subject)
            ->map(fn ($combo) => ['name' => $data['subjects'][$combo['subject']]['name'], 'url' => $combo['url'], 'count' => $combo['count']])
            ->all();

        return $this->landing($page['heading'], $this->catalog->tutors($subjectPage['id'], $directPage['id']), [
            ['name' => 'Репетиторы', 'url' => route('catalog.index')],
            ['name' => $subjectPage['name'], 'url' => $subjectPage['url']],
            ['name' => $directPage['name'], 'url' => $page['url']],
        ], [
            ['title' => 'Весь раздел', 'links' => [
                ['name' => $subjectPage['heading'], 'url' => $subjectPage['url'], 'count' => $subjectPage['count']],
                ['name' => $directPage['heading'], 'url' => $directPage['url'], 'count' => $directPage['count']],
            ]],
            ['title' => $subjectPage['name'] . ': другие направления', 'links' => $siblings],
            ['title' => $directPage['name'] . ': другие предметы', 'links' => $sameDirect],
        ], $page['indexable'] ? null : 'noindex, follow');
    }

    private function landing(string $heading, $tutors, array $breadcrumbs, array $related, ?string $robots = null)
    {
        $stats = $this->catalog->stats($tutors);
        $facts = $this->catalog->factsLine($stats);

        $title = $heading . ' онлайн — ' . plural_ru($stats['count'], 'репетитор', 'репетитора', 'репетиторов')
            . ($stats['price_min'] !== null ? ', от ' . number_format($stats['price_min'], 0, ',', ' ') . ' ₽ за занятие' : '')
            . ' | ' . Seo::siteName();

        $description = $heading . ' онлайн на ' . Seo::siteName() . ': ' . implode(', ', $facts) . '. '
            . 'Сравните учителей по цене, отзывам и классам и договоритесь о занятиях напрямую. '
            . 'Занятия в браузере с интерактивной доской и записью урока.';

        return view('catalog.landing', [
            'heading' => $heading,
            'title' => $title,
            'description' => $description,
            'robots' => $robots,
            'breadcrumbs' => $breadcrumbs,
            'tutors' => $tutors,
            'stats' => $stats,
            'facts' => $facts,
            'related' => array_values(array_filter($related, fn ($group) => !empty($group['links']))),
            'faq' => $this->catalog->faq($stats),
        ]);
    }

    private function otherSubjects(string $except): array
    {
        return collect($this->catalog->catalog()['subjects'])
            ->reject(fn ($item) => $item['slug'] === $except)
            ->sortByDesc('count')
            ->map(fn ($item) => ['name' => $item['name'], 'url' => $item['url'], 'count' => $item['count']])
            ->values()
            ->all();
    }
}
