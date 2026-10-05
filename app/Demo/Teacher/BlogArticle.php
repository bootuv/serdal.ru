<?php

namespace App\Demo\Teacher;

use App\Demo\Concerns\TeacherBlogDemo;
use App\Demo\Screen;
use App\Models\BlogPost;
use App\Support\HumanDate;

/**
 * Статья учителя — App\Livewire\Cabinet\Teacher\BlogArticle (шаблон админского экрана с teacher = true).
 * {id}: 301 — на сайте (только чтение, «Снять с сайта и изменить»), 302 — на проверке, 303 — черновик, new — новая.
 * Текст черновика и статьи на проверке — в блочном редакторе (x-ui.block-editor): ему нужен resources/js/block-editor.js.
 * Состояние: ?confirmDelete=1 — окно удаления, ?tags=… — добавленные теги.
 */
class BlogArticle extends Screen
{
    use TeacherBlogDemo;

    public const PATH = 'blog/{id}';

    public const EXAMPLES = ['blog/301', 'blog/302', 'blog/303', 'blog/new', 'blog/303?confirmDelete=1'];

    public string $view = 'livewire.cabinet.admin.blog-article';

    public string $title = 'Статья';

    public ?string $active = 'blog';

    public function modalParams(): array
    {
        return ['confirmDelete'];
    }

    public function actions(): array
    {
        return [
            'submit' => ['toast' => 'Статья отправлена на проверку — пришлем уведомление, когда ее опубликуют'],
            'unpublish' => ['toast' => 'Статья снята с сайта — можно править и отправить на проверку снова'],
            'withdraw' => ['toast' => 'Статья снята с проверки — это снова черновик'],
            'askDelete' => ['set' => ['confirmDelete' => '1']],
            'delete' => ['go' => route('cabinet.teacher.blog'), 'toast' => 'Статья удалена'],
            'addTag' => ['add' => 'tags'],
        ];
    }

    /** Значения $wire.… для Alpine: редактор берёт текст через $wire.entangle('body'). */
    public function props(): array
    {
        $data = $this->post();

        return ['body' => $data['body'], 'title' => $data['title'], 'tags' => $data['tags']];
    }

    /** Поля статьи (как mount() настоящего экрана) и модель. */
    private function post(): array
    {
        $model = self::blogPost((int) $this->param('id'));
        $tags = $model ? $model->tags->pluck('name')->all() : [];
        // Добавленные в демо теги (addTag) — из адреса
        foreach ($this->state('tags', []) as $tag) {
            $tag = trim($tag);
            if ($tag !== '' && count($tags) < 8 && ! in_array(mb_strtolower($tag), array_map('mb_strtolower', $tags), true)) {
                $tags[] = mb_substr($tag, 0, 40);
            }
        }

        return [
            'model' => $model,
            'title' => $model?->title ?? '',
            'body' => (string) $model?->body,
            'slug' => $model?->slug ?? '',
            'excerpt' => (string) $model?->excerpt,
            'tags' => $tags,
        ];
    }

    public function data(): array
    {
        $post = $this->post();
        $model = $post['model'];
        $status = match (true) {
            ! $model => 'new',
            $model->isPublished() => 'published',
            $model->isScheduled() => 'scheduled',
            $model->review_status === BlogPost::REVIEW_PENDING => 'pending',
            $model->review_status === BlogPost::REVIEW_RETURNED => 'returned',
            default => 'draft',
        };
        $editable = ! $model || (! $model->isPublished() && ! $model->isScheduled());

        return [
            // render()
            'model' => $model,
            'teacher' => true,
            'editable' => $editable,
            'status' => $status,
            'backUrl' => route('cabinet.teacher.blog'),
            'facts' => match ($status) {
                'published' => 'На сайте с ' . HumanDate::date($model->published_at) . ' · ' . plural_ru($model->views_count, 'просмотр', 'просмотра', 'просмотров'),
                'scheduled' => 'Выйдет ' . HumanDate::at($model->published_at),
                'pending' => 'На проверке с ' . HumanDate::at($model->submitted_at ?? $model->updated_at),
                'returned' => 'Вернули на доработку',
                'draft' => 'Черновик — виден только вам',
                default => null,
            },
            'authors' => [],
            'tagOptions' => ['ЕГЭ', 'ОГЭ', 'Алгебра', 'Геометрия', 'Математика', 'Физика', 'Текстовые задачи', 'Подготовка к экзаменам', 'Мотивация'],
            'dirty' => false,
            // «Как на сайте» у учителя — только у опубликованной; ведёт за пределы демо (тост)
            'siteUrl' => $status === 'published' ? $model->url : null,
            'slugPreview' => $post['slug'] ?: 'statya',
            'descriptionLength' => mb_strlen(trim($post['excerpt'])),
            'submitLabel' => $status === 'pending' ? 'Обновить на проверке' : 'Отправить на проверку',
            // Публичные свойства компонента
            'postId' => $model?->id,
            'title' => $post['title'],
            'body' => $post['body'],
            'slug' => $post['slug'],
            'excerpt' => $post['excerpt'],
            'coverUrl' => '',
            'tags' => $post['tags'],
            'newTag' => '',
            'authorId' => (string) $model?->author_id,
            'when' => 'now',
            'publishAt' => '',
            'image' => null,
            'cover' => null,
            'video' => null,
            'confirmDelete' => $model && $this->state('confirmDelete', false),
            'returning' => false,
            'returnNote' => '',
            'savedHash' => '',
            'savedAt' => null,
        ];
    }
}
