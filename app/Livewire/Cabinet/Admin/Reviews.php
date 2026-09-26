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
    ];

    #[Url(except: AdminReviewsService::TAB_REPORTS)]
    public string $tab = AdminReviewsService::TAB_REPORTS;

    #[Url(except: '')]
    public string $q = '';

    public int $limit = self::PAGE;

    #[Locked]
    public ?int $openId = null;

    /** Шаг окна: view · hide · keep. */
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

    public function hide(): void
    {
        $review = $this->review((int) $this->openId);
        $reported = (bool) $review->is_reported;
        $notify = $reported && $this->notify;

        $this->service()->hide($review, $notify);
        $this->close();
        $this->toast = 'Отзыв скрыт' . ($notify ? ' — ' . $this->teacherName($review) . ' получит письмо' : '');
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
        $this->toast = 'Отзыв снова виден на странице учителя ' . ($review->teacher?->name ?? '');
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
        $review = Review::with(['user:id,name', 'teacher:id,name,first_name'])->find($id);
        abort_unless($review, 404);

        return $review;
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
            'rows' => $reviews->map(fn (Review $r) => $this->row($r)),
            'more' => max(0, $total - $this->limit),
            'emptyText' => match (true) {
                $term !== '' => 'Ничего не нашли — проверьте имя ученика или учителя',
                $this->tab === AdminReviewsService::TAB_REPORTS => 'Жалоб нет — новые появятся здесь',
                default => 'Пока пусто',
            },
            'r' => $opened ? $this->details($opened) : null,
        ]);
    }

    private function row(Review $r): array
    {
        return [
            'id' => $r->id,
            'user' => $r->user,
            'student' => $r->user?->name ?? 'Ученик',
            'pair' => ($r->user?->name ?? 'Ученик') . ' → ' . ($r->teacher?->name ?? 'Учитель'),
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
        $student = $r->user?->name ?? 'Ученик';
        $teacher = $r->teacher?->name ?? 'Учитель';
        $short = $this->teacherName($r);
        $status = match (true) {
            (bool) $r->is_rejected => 'hidden',
            (bool) $r->is_reported => 'reported',
            default => 'visible',
        };

        return $this->row($r) + [
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
}
