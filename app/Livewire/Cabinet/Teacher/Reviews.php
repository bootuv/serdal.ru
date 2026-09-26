<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\Review;
use App\Services\StudentPerformanceService;
use App\Services\TeacherReviewsService;
use App\Support\HumanDate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Отзывы учеников об учителе. Макеты: TeacherReviews, RvTeacherOpen, RvShareDesktop, RvReport, RvTeacherEmpty. */
#[Layout('components.layouts.cabinet', ['title' => 'Отзывы', 'active' => 'reviews'])]
class Reviews extends Component
{
    use TeacherScreen;

    private const PAGE = 20;

    public int $limit = self::PAGE;

    /** Отзыв, открытый целиком. */
    public ?int $openId = null;

    /** Отзыв в окне «Поделиться». */
    public ?int $shareId = null;

    /** Отзыв в окне «Пожаловаться». */
    public ?int $reportId = null;

    public function mount(): void
    {
        $this->authorizeTeacher();
    }

    /** «Читать полностью»: открытие окна считается прочтением. */
    public function read(int $id): void
    {
        $review = $this->own($id);
        $this->service()->markRead($review);
        $this->openId = $review->id;
    }

    public function share(int $id): void
    {
        $this->shareId = $this->own($id)->id;
        $this->openId = null;
    }

    public function askReport(int $id): void
    {
        $review = $this->own($id);
        if (! $review->is_reported) {
            $this->reportId = $review->id;
            $this->openId = null;
        }
    }

    public function sendReport(): void
    {
        $review = $this->own((int) $this->reportId);
        $this->service()->report($review, auth()->user());
        $this->reportId = null;
        $this->dispatch('toast', message: 'Жалоба отправлена');
    }

    public function showMore(): void
    {
        $this->limit += self::PAGE;
    }

    public function render()
    {
        $teacher = auth()->user();
        $query = fn () => $this->service()->query($teacher)->with('user:id,name');

        $fresh = $query()->whereNull('teacher_read_at')->latest()->orderByDesc('id')->get();
        $read = $query()->whereNotNull('teacher_read_at')->latest()->orderByDesc('id')->limit($this->limit + 1)->get();
        $readTotal = $this->service()->query($teacher)->whereNotNull('teacher_read_at')->count();
        $page = $teacher->username ? route('tutors.show', $teacher->username) : null;

        return view('livewire.cabinet.teacher.reviews', [
            'page' => $page,
            'pageLabel' => $page ? preg_replace('#^https?://#', '', $page) : null,
            'fresh' => $fresh->map(fn (Review $r) => $this->row($r)),
            'all' => $read->take($this->limit)->map(fn (Review $r) => $this->row($r)),
            'more' => max(0, $readTotal - $this->limit),
            'summary' => $this->service()->summary($teacher),
            'opened' => $this->openId ? $this->details($this->own($this->openId)) : null,
            'shared' => $this->shareId ? $this->row($this->own($this->shareId)) : null,
            'reported' => $this->reportId ? $this->row($this->own($this->reportId)) : null,
        ]);
    }

    private function row(Review $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->user?->name ?? 'Ученик',
            'user' => $r->user,
            'rating' => (int) $r->rating,
            'text' => (string) $r->text,
            'date' => $r->created_at ? HumanDate::day($r->created_at) : null,
            'at' => $r->created_at ? HumanDate::at($r->created_at) : null,
            'reported' => (bool) $r->is_reported,
            'shareUrl' => route('reviews.share-card', $r),
        ];
    }

    /** Для окна «Отзыв»: сколько занятий посетил ученик. */
    private function details(Review $r): array
    {
        $lessons = $r->user ? app(StudentPerformanceService::class)->stats($r->user, auth()->id())['lessons_attended'] : 0;

        return $this->row($r) + [
            'meta' => $lessons > 0 ? plural_ru($lessons, 'занятие', 'занятия', 'занятий') . ' с вами' : null,
        ];
    }

    private function own(int $id): Review
    {
        $review = $this->service()->query(auth()->user())->with('user:id,name')->find($id);
        abort_unless($review, 404);

        return $review;
    }

    private function service(): TeacherReviewsService
    {
        return app(TeacherReviewsService::class);
    }
}
