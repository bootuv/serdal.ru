<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\Review;
use App\Services\AdminReviewsService;
use App\Support\HumanDate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Отзывы и жалобы учителей на них: «Жалобы / Все отзывы / Скрытые», окно отзыва,
 * «Оставить отзыв» (снять жалобу), «Скрыть отзыв», «Вернуть отзыв», письмо учителю о решении.
 * «О платформе» — отзывы учителей о Serdal: проверка и публикация на /reviews («Опубликовать», «Снять с сайта»).
 * «Поделиться» — картинка для сторис и подпись, как у учителя: для видимых отзывов учеников и опубликованных о платформе.
 * Макет: AdminReviews. Логика — AdminReviewsService.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Жалобы на отзывы', 'active' => 'reviews'])]
class Reviews extends Component
{
    use AdminScreen;

    private const PAGE = 20;

    private const TABS = [
        AdminReviewsService::TAB_REPORTS => 'Жалобы',
        AdminReviewsService::TAB_ALL => 'Все отзывы',
        AdminReviewsService::TAB_HIDDEN => 'Скрытые',
        AdminReviewsService::TAB_PLATFORM => 'О платформе',
    ];

    #[Url(except: AdminReviewsService::TAB_REPORTS)]
    public string $tab = AdminReviewsService::TAB_REPORTS;

    #[Url(except: '')]
    public string $q = '';

    public int $limit = self::PAGE;

    #[Locked]
    public ?int $openId = null;

    /** Шаг окна: view · hide · keep · share. */
    #[Locked]
    public string $step = '';

    /** «Сообщить учителю о решении». */
    public bool $notify = true;

    #[Locked]
    public ?string $toast = null;

    /** Скрытый только что отзыв и была ли на нём жалоба — для «Отменить». */
    #[Locked]
    public ?array $undo = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = AdminReviewsService::TAB_REPORTS;
        }
    }

    public function updatedTab(): void
    {
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = AdminReviewsService::TAB_REPORTS;
        }
        $this->limit = self::PAGE;
    }

    public function updatedQ(): void
    {
        $this->limit = self::PAGE;
    }

    public function more(): void
    {
        $this->limit += self::PAGE;
    }

    public function open(int $id): void
    {
        $this->review($id);
        $this->openId = $id;
        $this->step = 'view';
        $this->notify = true;
        $this->hideToast();
    }

    public function close(): void
    {
        $this->openId = null;
        $this->step = '';
    }

    public function back(): void
    {
        $this->step = 'view';
    }

    public function toHide(): void
    {
        abort_if($this->review((int) $this->openId)->is_rejected, 404);
        $this->step = 'hide';
    }

    public function toKeep(): void
    {
        abort_unless($this->review((int) $this->openId)->is_reported, 404);
        $this->step = 'keep';
    }

    /** «Поделиться» на компьютере — окно с картинкой для сторис и подписью. */
    public function toShare(): void
    {
        abort_unless($this->shareable($this->review((int) $this->openId)), 404);
        $this->step = 'share';
    }

    /** «Опубликовать» отзыв о платформе — появится на странице отзывов. */
    public function approve(): void
    {
        $review = $this->review((int) $this->openId);
        abort_unless($review->isPlatform() && $review->show_on_site && ! $review->approved_at, 404);

        $this->service()->approve($review);
        $this->close();
        $this->toast = 'Отзыв опубликован на странице отзывов';
        $this->undo = null;
    }

    public function hide(): void
    {
        $review = $this->review((int) $this->openId);
        $reported = (bool) $review->is_reported;
        $notify = $reported && $this->notify;

        $this->service()->hide($review, $notify);
        $this->close();
        $this->toast = ($review->isPlatform() ? 'Отзыв не будет показан на сайте' : 'Отзыв скрыт') . ($notify ? ' — ' . $this->teacherName($review) . ' получит письмо' : '');
        // Отменить можно, пока учителю не ушло письмо о решении
        $this->undo = $notify ? null : ['id' => $review->id, 'is_reported' => $reported];
    }

    public function keep(): void
    {
        $review = $this->review((int) $this->openId);
        abort_unless($review->is_reported, 404);
        $notify = $this->notify;

        $this->service()->keep($review, $notify);
        $this->close();
        $this->toast = 'Жалоба снята, отзыв остаётся' . ($notify ? ' — ' . $this->teacherName($review) . ' получит письмо' : '');
        $this->undo = null;
    }

    public function restore(): void
    {
        $review = $this->review((int) $this->openId);
        abort_unless($review->is_rejected, 404);

        $this->service()->restore($review);
        $this->close();
        $this->toast = $review->isPlatform()
            ? ($review->fresh()->approved_at ? 'Отзыв снова на странице отзывов' : 'Отзыв снова ждёт проверки')
            : 'Отзыв снова виден на странице учителя ' . ($review->teacher?->name ?? '');
        $this->undo = null;
    }

    public function undoHide(): void
    {
        if ($this->undo) {
            $review = $this->review((int) $this->undo['id']);
            if ($review->is_rejected) {
                $this->service()->undoHide($review, $this->undo);
            }
        }
        $this->hideToast();
    }

    public function hideToast(): void
    {
        $this->toast = null;
        $this->undo = null;
    }

    private function service(): AdminReviewsService
    {
        return app(AdminReviewsService::class);
    }

    private function review(int $id): Review
    {
        $review = Review::with(['user:id,name', 'teacher:id,name,first_name,username'])->find($id);
        abort_unless($review, 404);

        return $review;
    }

    /** Делиться можно тем, что видно на сайте: отзыв ученика без жалобы и не скрытый, отзыв о платформе — опубликованный. */
    private function shareable(Review $r): bool
    {
        if ($r->is_rejected) {
            return false;
        }

        return $r->isPlatform() ? $r->show_on_site && $r->approved_at !== null : ! $r->is_reported;
    }

    /** Для окна «Поделиться»: картинка, подпись и адрес страницы, где видны все отзывы. */
    private function share(Review $r): array
    {
        $page = match (true) {
            $r->isPlatform() => route('reviews'),
            (bool) $r->teacher?->username => route('tutors.show', $r->teacher->username),
            default => null,
        };

        return [
            'shareable' => $this->shareable($r),
            'shareUrl' => route('reviews.share-card', $r),
            'name' => $r->user?->name ?? ($r->isPlatform() ? 'Учитель' : 'Ученик'),
            'day' => $r->created_at ? HumanDate::day($r->created_at) : '',
            'pageLabel' => $page ? preg_replace('#^https?://#', '', $page) : null,
        ];
    }

    /** Имя учителя для текстов: «Мария». */
    private function teacherName(Review $review): string
    {
        $teacher = $review->teacher;

        return $teacher?->first_name ?: ($teacher?->name ?? 'Учитель');
    }

    public function render()
    {
        $query = $this->service()->query($this->tab, $this->q);
        $total = (clone $query)->count();
        $reviews = $query->limit($this->limit)->get();

        $opened = $this->openId ? $this->review($this->openId) : null;
        $term = trim($this->q);

        return view('livewire.cabinet.admin.reviews', [
            'tabs' => self::TABS,
            'reportsCount' => $this->service()->reportsCount(),
            'platformCount' => $this->service()->platformPendingCount(),
            'rows' => $reviews->map(fn (Review $r) => $this->row($r)),
            'more' => max(0, $total - $this->limit),
            'emptyText' => match (true) {
                $term !== '' => 'Ничего не нашли — проверьте имя ученика или учителя',
                $this->tab === AdminReviewsService::TAB_REPORTS => 'Жалоб нет — новые появятся здесь',
                $this->tab === AdminReviewsService::TAB_PLATFORM => 'Учителя ещё не оставляли отзывов о платформе',
                default => 'Пока пусто',
            },
            'r' => $opened ? $this->details($opened) : null,
        ]);
    }

    private function row(Review $r): array
    {
        $platform = $r->isPlatform();

        return [
            'platform' => $platform,
            'platformStatus' => $platform ? match (true) {
                (bool) $r->is_rejected => 'Скрыт',
                ! $r->show_on_site => 'Только для команды',
                $r->approved_at !== null => 'На сайте',
                default => 'Ждёт проверки',
            } : null,
            'id' => $r->id,
            'user' => $r->user,
            'student' => $r->user?->name ?? 'Ученик',
            'pair' => ($r->user?->name ?? 'Ученик') . ' → ' . ($platform ? 'Serdal' : ($r->teacher?->name ?? 'Учитель')),
            'rating' => (int) $r->rating,
            'date' => $r->created_at ? HumanDate::at($r->created_at) : '',
            'text' => (string) $r->text,
            'reported' => $r->is_reported && ! $r->is_rejected,
            'reason' => $r->report_reason_label ?? ($r->is_reported ? 'Причина не указана' : null),
            'reportedAt' => $r->reported_at ? HumanDate::at($r->reported_at) : null,
            'hidden' => (bool) $r->is_rejected,
            'hiddenNote' => $r->is_rejected
                ? 'Скрыт ' . ($r->hidden_at ? ($r->hidden_at->isToday() ? 'сегодня' : HumanDate::date($r->hidden_at)) : '')
                    . ($r->report_reason_label ? ' · жалоба: ' . mb_strtolower($r->report_reason_label) : '')
                : null,
        ];
    }

    private function details(Review $r): array
    {
        if ($r->isPlatform()) {
            return $this->platformDetails($r);
        }

        $student = $r->user?->name ?? 'Ученик';
        $teacher = $r->teacher?->name ?? 'Учитель';
        $short = $this->teacherName($r);
        $status = match (true) {
            (bool) $r->is_rejected => 'hidden',
            (bool) $r->is_reported => 'reported',
            default => 'visible',
        };

        return $this->row($r) + $this->share($r) + [
            'status' => $status,
            'title' => 'Отзыв: ' . $student,
            'sub' => 'Учитель ' . $teacher . ($r->created_at ? ' · ' . HumanDate::at($r->created_at) : ''),
            'teacherShort' => $short,
            'hasReport' => $status !== 'visible' && ($status === 'reported' || $r->report_reason),
            'reportTitle' => $status === 'reported' ? 'Жалоба учителя' : 'Была жалоба учителя',
            'reportBy' => $teacher . ($r->reported_at ? ' · ' . HumanDate::at($r->reported_at) : ''),
            'note' => filled($r->report_note) ? (string) $r->report_note : null,
            'hiddenText' => ($r->hidden_at ? 'Скрыт ' . ($r->hidden_at->isToday() ? 'сегодня' : HumanDate::date($r->hidden_at)) . '. ' : '')
                . $student . ' не может оставить учителю новый отзыв.',
            'hideText' => 'Отзыв пропадёт со страницы учителя ' . $teacher . ' и из оценки. ' . $student
                . ' не сможет оставить новый отзыв этому учителю. Вернуть отзыв можно во вкладке «Скрытые».',
            'keepText' => 'Жалоба будет снята, отзыв останется на странице учителя ' . $teacher . '.',
        ];
    }

    /** Окно отзыва учителя о платформе. */
    private function platformDetails(Review $r): array
    {
        $author = $r->user?->name ?? 'Учитель';
        $status = match (true) {
            (bool) $r->is_rejected => 'hidden',
            ! $r->show_on_site => 'private',
            $r->approved_at !== null => 'published',
            default => 'pending',
        };

        return $this->row($r) + $this->share($r) + [
            'status' => $status,
            'title' => 'Отзыв о Serdal',
            'sub' => 'Учитель ' . $author . ($r->updated_at ? ' · ' . HumanDate::at($r->updated_at) : ''),
            'teacherShort' => $author,
            'hasReport' => false,
            'note' => null,
            'platformText' => match ($status) {
                'pending' => 'Автор разрешил показать отзыв на сайте с именем и фото. После публикации он появится на странице отзывов.',
                'published' => 'Отзыв на странице отзывов' . ($r->approved_at ? ' с ' . HumanDate::date($r->approved_at) : '') . '.',
                'private' => 'Автор не разрешил показывать отзыв на сайте — его видит только команда Serdal.',
                default => 'Отзыв скрыт и не показывается на сайте.',
            },
            'hiddenText' => 'Отзыв скрыт и не показывается на сайте.',
            'hideText' => 'Отзыв не будет показан на странице отзывов. Вернуть его можно в этой же вкладке.',
            'keepText' => '',
        ];
    }
}
