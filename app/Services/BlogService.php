<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use App\Notifications\BlogPostReviewed;
use App\Support\RichText;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Блог на сайте (serdal.ru/blog): статьи для поиска. Пишут админ (админка «Блог») и учителя (кабинет → «Блог»),
 * редактор — x-ui.block-editor. Сайт показывает опубликованные (BlogController), опубликованные попадают
 * в sitemap и IndexNow (SitemapService) и в llms.txt.
 *
 * Статья учителя проходит проверку: черновик → «Отправить на проверку» (pending) → админ публикует или возвращает
 * с комментарием (returned), учителю приходит уведомление. Опубликованную статью учитель не меняет —
 * снимает с публикации, правит и отправляет снова. Админ пишет сам и может назначить автором любого учителя.
 */
class BlogService
{
    public const IMAGE_DIR = 'blog';

    public const PER_PAGE = 15;

    /** Страница темы попадает в поиск и карту сайта, только если в теме столько статей (иначе она повторяет статью). */
    public const TAG_MIN_POSTS = 2;

    /**
     * Сохранить статью. $data: title, slug, excerpt, cover_url, body, published_at (null — черновик), tags (названия),
     * author_id (только у админа: учитель или null — «Команда Serdal»).
     * Адрес — из заголовка, если не задан; у сохраненной статьи без ручного адреса он не меняется.
     */
    public function save(?BlogPost $post, array $data, ?User $by = null): BlogPost
    {
        $isNew = ! $post;
        $post ??= new BlogPost(['created_by' => $by?->id]);

        $title = trim((string) $data['title']);
        $slug = BlogPost::toSlug(trim((string) ($data['slug'] ?? '')));
        $slug = $slug !== '' ? BlogPost::slugFrom($slug, $post->id) : ($post->slug ?: BlogPost::slugFrom($title, $post->id));

        $fields = [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => trim((string) ($data['excerpt'] ?? '')) ?: null,
            'cover_url' => trim((string) ($data['cover_url'] ?? '')) ?: null,
            'body' => RichText::clean($data['body'] ?? null),
        ];
        if (array_key_exists('published_at', $data)) {
            $fields['published_at'] = $data['published_at'];
        }
        if (array_key_exists('author_id', $data)) {
            $fields['author_id'] = $data['author_id'] ? User::whereKey($data['author_id'])->where('role', User::ROLE_TUTOR)->value('id') : null;
        }
        // Новая статья учителя: он автор, статья проходит проверку
        if ($isNew && $by?->role === User::ROLE_TUTOR) {
            $fields['author_id'] = $by->id;
            $fields['review_status'] = BlogPost::REVIEW_DRAFT;
            $fields['published_at'] = null;
        }

        $oldSlug = $post->slug;
        $wasPublished = $post->exists && $post->isPublished();

        DB::transaction(function () use ($post, $fields, $data, $oldSlug, $wasPublished) {
            $post->fill($fields)->save();
            if (array_key_exists('tags', $data)) {
                $this->syncTags($post, (array) $data['tags']);
            }
            // Адрес теперь занят этой статьей; прежний адрес опубликованной статьи ведет на новый
            DB::table('blog_slug_redirects')->where('slug', $post->slug)->delete();
            if ($wasPublished && $oldSlug && $oldSlug !== $post->slug) {
                DB::table('blog_slug_redirects')->updateOrInsert(['slug' => $oldSlug], ['blog_post_id' => $post->id, 'updated_at' => now(), 'created_at' => now()]);
            }
        });
        $this->refreshHotScore($post);
        $this->pingSearchEngines($post);
        $this->notifyStudents($post);

        return $post;
    }

    /** Статья на сайте — сразу сообщить Яндексу и Bing (IndexNow), не дожидаясь утренней отправки. Запланированные уйдут с ней. */
    private function pingSearchEngines(BlogPost $post): void
    {
        $indexNow = app(IndexNowService::class);
        if (! $post->isPublished() || ! $indexNow->enabled()) {
            return;
        }

        $urls = [Seo::url(route('blog.show', $post->slug, false)), Seo::url(route('blog.index', [], false))];
        dispatch(fn () => $indexNow->submit($urls))->afterResponse();
    }

    /** Статья по прежнему адресу (адрес сменили после публикации). */
    public function findByOldSlug(string $slug): ?BlogPost
    {
        $id = DB::table('blog_slug_redirects')->where('slug', $slug)->value('blog_post_id');

        return $id ? BlogPost::published()->find($id) : null;
    }

    public function unpublish(BlogPost $post): void
    {
        $post->update(['published_at' => null, 'review_status' => $post->review_status ? BlogPost::REVIEW_DRAFT : null]);
    }

    public function delete(BlogPost $post): void
    {
        $post->delete();
    }

    /* ---------- Проверка статей учителей ---------- */

    /** Учитель отправил статью на проверку. */
    public function submit(BlogPost $post): void
    {
        $post->update(['review_status' => BlogPost::REVIEW_PENDING, 'submitted_at' => now(), 'review_note' => null]);
    }

    /** Учитель забрал статью с проверки — снова черновик. */
    public function withdraw(BlogPost $post): void
    {
        $post->update(['review_status' => BlogPost::REVIEW_DRAFT, 'submitted_at' => null]);
    }

    /** Админ вернул статью учителю с комментарием. */
    public function returnForRework(BlogPost $post, string $note): void
    {
        $post->update(['review_status' => BlogPost::REVIEW_RETURNED, 'review_note' => trim($note) ?: null, 'published_at' => null]);
        $post->author?->notify(new BlogPostReviewed($post, published: false));
    }

    /** Статья учителя вышла (сразу или по времени) — сообщить автору один раз. */
    public function notifyPublished(BlogPost $post, bool $wasPublished): void
    {
        if ($post->review_status && ! $wasPublished && $post->published_at) {
            $post->update(['review_note' => null]);
            $post->author?->notify(new BlogPostReviewed($post, published: true));
        }
    }

    /**
     * Ученики учителя-автора узнают о вышедшей статье — один раз (students_notified_at), даже если статью снимут и вернут.
     * Вызывается после публикации и командой blog:notify раз в минуту (для статей по расписанию).
     */
    public function notifyStudents(BlogPost $post): bool
    {
        if (! $post->isPublished() || $post->students_notified_at || ! $post->author_id) {
            return false;
        }
        // Отмечаем атомарно: две параллельные команды не разошлют дважды
        if (! BlogPost::whereKey($post->id)->whereNull('students_notified_at')->update(['students_notified_at' => now()])) {
            return false;
        }

        $post->loadMissing('author');
        $post->author?->students()
            ->where(fn ($q) => $q->where('is_blocked', false)->orWhereNull('is_blocked'))
            ->chunkById(200, fn ($students) => \Illuminate\Support\Facades\Notification::send($students, new \App\Notifications\TeacherPublishedBlogPost($post)), 'users.id', 'id');

        return true;
    }

    /** Статьи учителей, время которых пришло, а ученики еще не знают (команда blog:notify). */
    public function notifyDue(): int
    {
        $n = 0;
        BlogPost::published()->whereNull('students_notified_at')->whereNotNull('author_id')->get()
            ->each(function (BlogPost $post) use (&$n) {
                $n += $this->notifyStudents($post) ? 1 : 0;
            });

        return $n;
    }

    /** Ждут проверки — для счетчика в меню админки. */
    public function pendingCount(): int
    {
        return BlogPost::whereNull('published_at')->where('review_status', BlogPost::REVIEW_PENDING)->count();
    }

    /** Статьи учителя в его кабинете. */
    public function forTeacher(User $teacher): Builder
    {
        return BlogPost::where(fn ($q) => $q->where('created_by', $teacher->id)->orWhere('author_id', $teacher->id));
    }

    /** Может ли учитель открыть статью: создал сам или назначен автором. */
    public function teacherCanSee(BlogPost $post, User $teacher): bool
    {
        return $post->created_by === $teacher->id || $post->author_id === $teacher->id;
    }

    /** Может ли учитель править: свою, неопубликованную и не запланированную. */
    public function teacherCanEdit(BlogPost $post, User $teacher): bool
    {
        return $this->teacherCanSee($post, $teacher) && ! $post->isPublished() && ! $post->isScheduled();
    }

    /* ---------- Теги ---------- */

    /** Привязать теги по названиям: новые создаются, регистр и пробелы не важны. Не больше 8 тегов. */
    public function syncTags(BlogPost $post, array $names): void
    {
        $ids = collect($names)
            ->map(fn ($n) => Str::limit(trim(preg_replace('/\s+/u', ' ', (string) $n)), 40, ''))
            ->filter()
            ->unique(fn ($n) => mb_strtolower($n))
            ->take(8)
            ->map(function (string $name) {
                $slug = BlogPost::toSlug($name) ?: 'teg';

                return BlogTag::firstOrCreate(['slug' => $slug], ['name' => $name])->id;
            });

        $post->tags()->sync($ids->all());
        $this->deleteUnusedTags();
    }

    /** Теги без статей не нужны. */
    private function deleteUnusedTags(): void
    {
        BlogTag::whereDoesntHave('posts')->delete();
    }

    /** Популярные теги: по числу опубликованных статей. */
    public function popularTags(int $limit = 20): Collection
    {
        return BlogTag::whereHas('posts', fn ($q) => $q->published())
            ->withCount(['posts as published_count' => fn ($q) => $q->published()])
            ->orderByDesc('published_count')->orderBy('name')
            ->limit($limit)->get();
    }

    /** Самые активные авторы: учителя с наибольшим числом опубликованных статей. */
    public function activeAuthors(int $limit = 6): Collection
    {
        return User::query()
            ->whereNotNull('username')
            ->whereHas('blogPosts', fn ($q) => $q->published())
            ->withCount(['blogPosts as published_count' => fn ($q) => $q->published()])
            ->orderByDesc('published_count')->orderBy('name')
            ->limit($limit)->get();
    }

    /** Все теги для админки: со счетчиками статей. */
    public function tags(): Collection
    {
        return BlogTag::withCount(['posts', 'posts as published_count' => fn ($q) => $q->published()])->orderBy('name')->get();
    }

    /** Переименовать тег. Если такое название уже есть — теги объединяются. */
    public function renameTag(BlogTag $tag, string $name): void
    {
        $name = trim($name);
        $slug = BlogPost::toSlug($name) ?: $tag->slug;
        $twin = BlogTag::where('slug', $slug)->whereKeyNot($tag->id)->first();

        DB::transaction(function () use ($tag, $name, $slug, $twin) {
            if ($twin) {
                $postIds = $tag->posts()->pluck('blog_posts.id');
                $twin->posts()->syncWithoutDetaching($postIds);
                $tag->delete();

                return;
            }
            // Адрес меняем, только пока по тегу нет опубликованных статей — чтобы не ломать ссылки
            $tag->update(['name' => $name] + ($tag->posts()->published()->exists() ? [] : ['slug' => $slug]));
        });
    }

    public function deleteTag(BlogTag $tag): void
    {
        $tag->delete();
    }

    /* ---------- Лайки и «Популярные» ---------- */

    /** Поставить или снять лайк. Возвращает, стоит ли лайк теперь. */
    public function toggleLike(BlogPost $post, User $user): bool
    {
        abort_unless($post->isPublished(), 404);

        $liked = DB::transaction(function () use ($post, $user) {
            $removed = DB::table('blog_post_likes')->where(['blog_post_id' => $post->id, 'user_id' => $user->id])->delete();
            if (! $removed) {
                DB::table('blog_post_likes')->insert(['blog_post_id' => $post->id, 'user_id' => $user->id, 'created_at' => now()]);
            }
            $post->update(['likes_count' => DB::table('blog_post_likes')->where('blog_post_id', $post->id)->count()]);

            return ! $removed;
        });
        $this->refreshHotScore($post);

        return $liked;
    }

    public function likedBy(BlogPost $post, ?User $user): bool
    {
        return $user && DB::table('blog_post_likes')->where(['blog_post_id' => $post->id, 'user_id' => $user->id])->exists();
    }

    /**
     * Вес статьи для «Популярных», как у Hacker News: (лайки + 2 × комментарии + 1) / (дней с публикации + 2)^1.5.
     * Комментарий весит вдвое больше лайка (обсуждение — сильнее интерес), «+1» дает свежим статьям без реакций место выше
     * старых, а возраст в степени 1.5 плавно опускает статью: через неделю при тех же реакциях вес ниже в ~5 раз.
     */
    public static function hotScore(int $likes, int $comments, ?\Carbon\CarbonInterface $publishedAt): float
    {
        if (! $publishedAt) {
            return 0.0;
        }
        $days = max(0, $publishedAt->diffInMinutes(now(), false)) / 1440;

        return ($likes + 2 * $comments + 1) / (($days + 2) ** 1.5);
    }

    public function refreshHotScore(BlogPost $post): void
    {
        $score = self::hotScore((int) $post->likes_count, (int) $post->comments_count, $post->published_at);
        if (abs($score - (float) $post->hot_score) > 1e-9) {
            BlogPost::whereKey($post->id)->update(['hot_score' => $score]);
            $post->hot_score = $score;
        }
    }

    /** Возраст меняет вес у всех — команда blog:hot пересчитывает раз в час. */
    public function refreshAllHotScores(): int
    {
        $n = 0;
        BlogPost::published()->select(['id', 'likes_count', 'comments_count', 'published_at', 'hot_score'])->chunkById(500, function ($posts) use (&$n) {
            foreach ($posts as $post) {
                $this->refreshHotScore($post);
                $n++;
            }
        });

        return $n;
    }

    /* ---------- Сайт ---------- */

    /** Еще статьи под текущей: сначала с общими тегами, потом свежие. */
    public function more(BlogPost $post, int $limit = 4): Collection
    {
        $tagIds = $post->tags()->pluck('blog_tags.id');
        $same = $tagIds->isNotEmpty()
            ? BlogPost::published()->with('author')->whereKeyNot($post->id)->whereHas('tags', fn ($q) => $q->whereIn('blog_tags.id', $tagIds))->latest('published_at')->limit($limit)->get()
            : collect();
        $rest = BlogPost::published()->with('author')->whereKeyNot($post->id)->whereNotIn('id', $same->pluck('id'))->latest('published_at')->limit($limit - $same->count())->get();

        return $same->concat($rest);
    }

    /** Самые популярные статьи (вес из лайков, комментариев и возраста) — для главной страницы сайта. */
    public function popular(int $limit = 4): Collection
    {
        return BlogPost::published()->with('author')->orderByDesc('hot_score')->latest('published_at')->limit($limit)->get();
    }

    /** Статья в Markdown: заголовок, автор, дата, адрес, описание, текст и теги — для ИИ-агентов и llms-full.txt. */
    public function markdown(BlogPost $post): string
    {
        $url = Seo::url(route('blog.show', $post->slug, false));
        $meta = array_filter([
            'Автор: ' . $post->authorName(),
            $post->published_at ? 'Опубликовано: ' . $post->published_at->toDateString() : null,
            'Обновлено: ' . $post->updated_at->toDateString(),
            'Адрес: ' . $url,
        ]);

        $out = ['# ' . $post->title, '', implode("\n", array_map(fn ($line) => '- ' . $line, $meta))];
        if (trim((string) $post->excerpt) !== '') {
            $out[] = '';
            $out[] = '> ' . Seo::text($post->excerpt, 500);
        }
        $out[] = '';
        $out[] = RichText::toMarkdown($post->body, Seo::baseUrl());
        if ($post->tags->isNotEmpty()) {
            $out[] = '';
            $out[] = 'Темы: ' . $post->tags->pluck('name')->implode(', ');
        }

        return implode("\n", $out) . "\n";
    }

    /* ---------- Картинки ---------- */

    /** Длинная сторона картинки после загрузки — не больше Full HD. */
    public const IMAGE_MAX_SIDE = 1920;

    /** Качество WebP: на глаз как оригинал, по весу в разы меньше JPG/PNG. */
    public const IMAGE_QUALITY = 80;

    /**
     * Картинка для статьи (обложка или в текст) — на CDN. JPG, PNG, WebP, BMP переводим в WebP и уменьшаем,
     * чтобы длинная сторона была не больше 1920 (пропорции сохраняются, маленькие не увеличиваем).
     * GIF оставляем как есть — иначе пропадет анимация. Не вышло перекодировать — грузим исходный файл.
     */
    public function storeImage(\Illuminate\Http\UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        if ($extension !== 'gif') {
            try {
                $webp = (string) Image::read($file->get())
                    ->scaleDown(width: self::IMAGE_MAX_SIDE, height: self::IMAGE_MAX_SIDE)
                    ->toWebp(self::IMAGE_QUALITY);
                $path = self::IMAGE_DIR . '/' . Str::lower(Str::random(24)) . '.webp';
                Storage::disk(HelpCenterService::DISK)->put($path, $webp, 'public');

                return Storage::disk(HelpCenterService::DISK)->url($path);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $path = $file->storePublicly(self::IMAGE_DIR, HelpCenterService::DISK);

        return Storage::disk(HelpCenterService::DISK)->url($path);
    }
}
