<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Services\BlogService;
use App\Services\TutorCatalogService;
use Illuminate\Console\Command;

/**
 * Демо-статьи блога для оценки вида (локально): авторы — учителя с открытой страницей и «Команда Serdal»,
 * теги, обложки (часть статей без обложки — цветные карточки), оформленный текст. Адреса начинаются с demo-.
 * Повторный запуск пересоздает набор, --remove убирает его.
 */
class BlogDemo extends Command
{
    protected $signature = 'blog:demo {--remove : Удалить демо-статьи} {--force : Разрешить на проде}';

    protected $description = 'Заполнить блог демо-статьями (или удалить их)';

    private const POSTS = [
        ['Как подготовиться к ОГЭ по математике за полгода', ['ОГЭ', 'математика'], 'oge-math', 'План по месяцам: от диагностики до пробников, без зубрежки и ночных марафонов.'],
        ['5 ошибок на пробнике ЕГЭ по русскому, которые легко исправить', ['ЕГЭ', 'русский язык'], null, 'Разбираем задания, где ученики теряют баллы чаще всего, и как это исправить за месяц.'],
        ['Дистанционные занятия: с чего начать учителю', ['учителям', 'онлайн'], 'online-start', 'Что нужно для первого онлайн-урока и как удержать внимание ученика по ту сторону экрана.'],
        ['Почему ученики забывают материал и что с этим делать', ['родителям', 'память'], null, 'Кривая забывания, интервальные повторения и простые приемы, которые работают дома.'],
        ['Онлайн-кружок: как собрать группу и не выгореть', ['онлайн', 'учителям'], 'circle', 'Опыт учителей, которые ведут кружки по вечерам: расписание, оплата, домашние задания.'],
        ['Английский для младших школьников: игры вместо правил', ['английский', 'начальная школа'], 'english', 'Пять игр, после которых дети сами просят еще одно занятие.'],
        ['Как родителям помочь с домашним заданием и не делать его за ребенка', ['родителям'], null, 'Где проходит граница между помощью и контролем — советы педагога-психолога.'],
        ['Физика без формул: объясняем законы на примерах из жизни', ['физика', 'ЕГЭ'], 'physics', 'Почему ученик понимает тему, когда видит ее на кухне, а не в учебнике.'],
        ['Расписание консультаций перед экзаменами: шаблон для учителя', ['учителям', 'ОГЭ', 'ЕГЭ'], null, 'Готовый шаблон на последние восемь недель и как договориться с учениками о времени.'],
        ['Логопед онлайн: работает ли это с дошкольниками', ['дошкольникам', 'логопед'], 'speech', 'Что получается по видео, что нет, и как подготовить ребенка к первому занятию.'],
        ['Сочинение ЕГЭ: структура, которая нравится экспертам', ['ЕГЭ', 'русский язык'], null, 'Шесть абзацев, которые закрывают все критерии, и примеры сильных аргументов.'],
        ['Как мотивировать подростка учиться, если ему «все равно»', ['родителям', 'мотивация'], 'teen', 'Разговор вместо давления: три вопроса, которые стоит задать ребенку сегодня.'],
    ];

    public function handle(BlogService $blog, TutorCatalogService $catalog): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Это прод — демо-статьи не нужны. Если очень нужно: --force');

            return self::FAILURE;
        }

        $removed = BlogPost::where('slug', 'like', 'demo-%')->get()->each->delete()->count();
        BlogTag::whereDoesntHave('posts')->delete();
        if ($this->option('remove')) {
            $this->info("Удалено демо-статей: {$removed}");

            return self::SUCCESS;
        }

        // Демо-статьи — не повод слать ученикам уведомления о «новых статьях учителя»
        \Illuminate\Support\Facades\Notification::fake();

        $authors = $catalog->publicTutorsQuery()->orderBy('id')->limit(4)->pluck('id')->all();

        foreach (self::POSTS as $i => [$title, $tags, $cover, $excerpt]) {
            // Каждая четвертая — от команды, остальные — по очереди от учителей
            $authorId = $authors && $i % 4 !== 3 ? $authors[$i % count($authors)] : null;
            $post = $blog->save(null, [
                'title' => $title,
                'slug' => 'demo-' . BlogPost::toSlug($title),
                'excerpt' => $excerpt,
                'cover_url' => $cover ? ($i === 2 ? asset('images/bg.jpg') : 'https://picsum.photos/seed/serdal-' . $cover . '/1200/900') : null,
                'body' => $this->body($title, $excerpt),
                'tags' => $tags,
                'author_id' => $authorId,
                'published_at' => now()->subDays($i * 4 + 1)->setTime(10, 0),
            ]);
            $post->update(['views_count' => random_int(40, 900)]);
        }

        $this->addReactions($blog);

        $this->info('Создано демо-статей: ' . count(self::POSTS) . ($authors ? ', авторов-учителей: ' . count($authors) : ' (учителей с открытой страницей нет — все от команды)'));
        $this->line('Блог: ' . route('blog.index') . ' · убрать: php artisan blog:demo --remove');

        return self::SUCCESS;
    }

    private const COMMENTS = [
        'Спасибо, очень вовремя — у нас как раз через месяц пробник.',
        'А можно пример такой диагностики? Хочу попробовать со своим учеником.',
        'Согласна про регулярность, проверено на собственном ребенке.',
        'Интересно, а как вы считаете, сколько тем в неделю реально для девятого класса?',
        'Сохранила себе, перешлю коллегам по кафедре.',
    ];

    private const REPLIES = [
        'Да, конечно, напишу отдельную статью с примерами.',
        'Обычно две, если ученик занимается еще и в школе.',
        'Спасибо за отзыв!',
    ];

    /** Лайки и обсуждения демо-статей: популярные получают больше, чтобы вкладка «Популярные» отличалась от «Новых». */
    private function addReactions(BlogService $blog): void
    {
        $people = \App\Models\User::whereIn('role', [\App\Models\User::ROLE_STUDENT, \App\Models\User::ROLE_TUTOR])
            ->where(fn ($q) => $q->where('is_blocked', false)->orWhereNull('is_blocked'))
            ->inRandomOrder()->limit(12)->get();
        if ($people->isEmpty()) {
            return;
        }

        $posts = BlogPost::where('slug', 'like', 'demo-%')->with('author')->get();
        foreach ($posts as $i => $post) {
            $weight = [5, 1, 4, 0, 6, 2, 3, 0, 1, 5, 2, 0][$i % 12];
            foreach ($people->shuffle()->take(min($people->count(), $weight * 2)) as $u) {
                \Illuminate\Support\Facades\DB::table('blog_post_likes')->insertOrIgnore(['blog_post_id' => $post->id, 'user_id' => $u->id, 'created_at' => now()]);
            }
            foreach ($people->shuffle()->take(min($people->count(), $weight)) as $k => $u) {
                $root = \App\Models\BlogComment::create([
                    'blog_post_id' => $post->id, 'user_id' => $u->id, 'body' => self::COMMENTS[($i + $k) % count(self::COMMENTS)],
                    'created_at' => $post->published_at->copy()->addHours(3 + $k * 5),
                ]);
                if ($post->author && $k % 2 === 0) {
                    \App\Models\BlogComment::create([
                        'blog_post_id' => $post->id, 'user_id' => $post->author->id, 'parent_id' => $root->id, 'reply_to_user_id' => $u->id,
                        'body' => self::REPLIES[$k % count(self::REPLIES)], 'created_at' => $root->created_at->copy()->addHours(2),
                    ]);
                }
            }
            $post->update([
                'likes_count' => \Illuminate\Support\Facades\DB::table('blog_post_likes')->where('blog_post_id', $post->id)->count(),
                'comments_count' => $post->comments()->count(),
            ]);
            $blog->refreshHotScore($post);
        }
    }

    /** Текст статьи со всеми видами блоков: заголовки, список, нумерованный список, цитата, выделенный блок, разделитель. */
    private function body(string $title, string $excerpt): string
    {
        return '<p>' . e($excerpt) . ' В этой статье — то, что мы видим на занятиях каждую неделю, и то, что реально помогает ученикам.</p>'
            . '<h2>С чего начать</h2>'
            . '<p>Первый шаг — понять, где ученик сейчас. Небольшая диагностика на 20 минут покажет пробелы лучше, чем оценки в дневнике.</p>'
            . '<ul><li>соберите задания из разных тем;</li><li>засеките время, но не торопите;</li><li>отметьте, где ученик сомневается, а не только где ошибся.</li></ul>'
            . '<aside class="callout">Совет: записывайте вывод после каждой диагностики в одну строку — через месяц будет видно, как ученик вырос.</aside>'
            . '<h2>План на ближайший месяц</h2>'
            . '<ol><li>Две темы в неделю, не больше.</li><li>Короткое повторение в начале каждого занятия.</li><li>Пробная работа в конце месяца.</li></ol>'
            . '<blockquote>«Регулярность важнее объема: 30 минут три раза в неделю дают больше, чем три часа в воскресенье».</blockquote>'
            . '<hr>'
            . '<h3>Что сказать ученику</h3>'
            . '<p>Объясните, зачем все это: цель, понятная ученику, работает лучше любого контроля. А мы в Serdal поможем с расписанием, заданиями и записями занятий.</p>';
    }
}
