<?php

namespace App\Services;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Support\HelpIcons;
use App\Support\RichText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

/**
 * База знаний (Help Center): категории, статьи, порядок, видео и картинки статей.
 * Используется новой админкой (/cabinet/admin/help). Порядок — поле sort_order; публичная справка сортирует по нему же.
 */
class HelpCenterService
{
    public const DISK = 's3';
    public const VIDEO_DIR = 'help-videos';
    public const IMAGE_DIR = 'help-articles';

    /** Категории раздела по порядку со статьями по порядку. */
    public function categories(string $audience): Collection
    {
        return HelpCategory::where('audience', $audience)
            ->with(['articles' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    /** Сохранить категорию (новую или существующую). $data: audience, name, description, icon, is_published. */
    public function saveCategory(?HelpCategory $category, array $data): HelpCategory
    {
        $values = [
            'audience' => $data['audience'],
            'name' => trim($data['name']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'icon' => $data['icon'] ?? null,
            'is_published' => (bool) ($data['is_published'] ?? true),
        ];

        if ($category) {
            // Перенос в другой раздел — в конец списка раздела
            if ($category->audience !== $values['audience']) {
                $values['sort_order'] = $this->nextCategoryOrder($values['audience']);
            }
            $category->update($values);

            return $category;
        }

        return HelpCategory::create($values + [
            'icon' => $values['icon'] ?: HelpIcons::DEFAULT,
            'sort_order' => $this->nextCategoryOrder($values['audience']),
        ]);
    }

    /** Удалить категорию вместе со статьями и их видео. */
    public function deleteCategory(HelpCategory $category): void
    {
        DB::transaction(function () use ($category) {
            $category->articles()->get()->each(fn (HelpArticle $a) => $this->deleteArticle($a));
            $category->delete();
        });
    }

    /** Перетаскивание категории к соседней в том же разделе. */
    public function moveCategory(HelpCategory $category, HelpCategory $target, bool $before): void
    {
        if ($category->id === $target->id || $category->audience !== $target->audience) {
            return;
        }

        $ids = HelpCategory::where('audience', $category->audience)->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
        $this->persistOrder(HelpCategory::class, $this->insert($ids, $category->id, $target->id, $before));
    }

    /** Перетаскивание статьи к соседней статье (в том числе из другой категории того же раздела). */
    public function moveArticle(HelpArticle $article, HelpArticle $target, bool $before): void
    {
        if ($article->id === $target->id) {
            return;
        }

        DB::transaction(function () use ($article, $target, $before) {
            if ($article->help_category_id !== $target->help_category_id) {
                $article->update(['help_category_id' => $target->help_category_id]);
            }
            $ids = HelpArticle::where('help_category_id', $target->help_category_id)->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
            $this->persistOrder(HelpArticle::class, $this->insert($ids, $article->id, $target->id, $before));
        });
    }

    /** Статья брошена на шапку категории — первой в этой категории. */
    public function moveArticleToCategory(HelpArticle $article, HelpCategory $category): void
    {
        DB::transaction(function () use ($article, $category) {
            $article->update(['help_category_id' => $category->id]);
            $ids = HelpArticle::where('help_category_id', $category->id)->where('id', '!=', $article->id)
                ->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
            $this->persistOrder(HelpArticle::class, array_merge([$article->id], $ids));
        });
    }

    /**
     * Сохранить статью. $data: help_category_id, title, excerpt, content (HTML из редактора), is_published,
     * video_source (file|link), video_url, video (новый файл или null), remove_video.
     */
    public function saveArticle(?HelpArticle $article, array $data): HelpArticle
    {
        $values = [
            'help_category_id' => (int) $data['help_category_id'],
            'title' => trim($data['title']),
            'excerpt' => filled($data['excerpt'] ?? null) ? trim($data['excerpt']) : null,
            // Картинки статьи (img) очистка сохраняет — см. HelpArticleTest
            'content' => RichText::clean($data['content'] ?? null),
            'is_published' => (bool) ($data['is_published'] ?? false),
        ];

        $oldFile = $article?->video_file;
        $newFile = $oldFile;

        if (($data['video_source'] ?? 'file') === 'link') {
            $values['video_url'] = filled($data['video_url'] ?? null) ? trim($data['video_url']) : null;
            $newFile = null;
        } else {
            $values['video_url'] = null;
            if (($data['video'] ?? null) instanceof UploadedFile) {
                $newFile = $data['video']->storePublicly(self::VIDEO_DIR, self::DISK);
            } elseif (! empty($data['remove_video'])) {
                $newFile = null;
            }
        }
        $values['video_file'] = $newFile;

        if ($article) {
            if ($article->help_category_id !== $values['help_category_id']) {
                $values['sort_order'] = $this->nextArticleOrder($values['help_category_id']);
            }
            $article->update($values);
        } else {
            // Слаг генерируется из заголовка (уникальный) — HelpArticle::booted()
            $article = HelpArticle::create($values + ['sort_order' => $this->nextArticleOrder($values['help_category_id'])]);
        }

        if ($oldFile && $oldFile !== $newFile) {
            Storage::disk(self::DISK)->delete($oldFile);
        }

        return $article;
    }

    public function setPublished(HelpArticle $article, bool $published): void
    {
        $article->update(['is_published' => $published]);
    }

    public function deleteArticle(HelpArticle $article): void
    {
        if ($article->video_file) {
            Storage::disk(self::DISK)->delete($article->video_file);
        }
        $article->delete();
    }

    /** Ширина картинки в статье: колонка текста уже, с запасом на экраны с высокой плотностью. */
    private const IMAGE_MAX_WIDTH = 1600;

    /**
     * Картинка в текст статьи: загружается на CDN, возвращается адрес для <img src>.
     * JPG, PNG и WebP сохраняются в WebP (легче при том же виде); GIF — как есть, чтобы не пропала анимация.
     * $dir — папка на CDN (новости кладут картинки в свою).
     */
    public function storeImage(UploadedFile $file, string $dir = self::IMAGE_DIR): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            try {
                $webp = (string) Image::read($file->get())->scaleDown(width: self::IMAGE_MAX_WIDTH)->toWebp(82);
                $path = $dir . '/' . Str::lower(Str::random(24)) . '.webp';
                Storage::disk(self::DISK)->put($path, $webp, 'public');

                return Storage::disk(self::DISK)->url($path);
            } catch (\Throwable $e) {
                // Нет поддержки WebP или картинка не читается — загружаем исходный файл
                report($e);
            }
        }

        $path = $file->storePublicly($dir, self::DISK);

        return Storage::disk(self::DISK)->url($path);
    }

    private function nextCategoryOrder(string $audience): int
    {
        return (int) HelpCategory::where('audience', $audience)->max('sort_order') + 1;
    }

    private function nextArticleOrder(int $categoryId): int
    {
        return (int) HelpArticle::where('help_category_id', $categoryId)->max('sort_order') + 1;
    }

    /** Список id: убрать $id и вставить перед/после $target. */
    private function insert(array $ids, int $id, int $target, bool $before): array
    {
        $ids = array_values(array_filter($ids, fn ($x) => (int) $x !== $id));
        $pos = array_search($target, array_map('intval', $ids), true);
        if ($pos === false) {
            $ids[] = $id;

            return $ids;
        }
        array_splice($ids, $before ? $pos : $pos + 1, 0, [$id]);

        return $ids;
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    private function persistOrder(string $model, array $ids): void
    {
        foreach (array_values($ids) as $i => $id) {
            // Без updated_at: перестановка — не правка статьи
            $model::whereKey($id)->toBase()->update(['sort_order' => $i + 1]);
        }
    }
}
