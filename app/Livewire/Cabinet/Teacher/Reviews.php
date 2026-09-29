<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\Review;
use App\Services\ReviewPromptService;
use App\Services\StudentPerformanceService;
use App\Services\TeacherReviewsService;
use App\Support\HumanDate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
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

    /** Отзыв в окне «Пожаловаться», причина (Review::REPORT_REASONS) и пояснение к «Другое». */
    public ?int $reportId = null;

    public string $reportReason = '';

    public string $reportNote = '';

    /** Поиск по имени ученика в «Все отзывы». */
    #[Url(except: '')]
    public string $search = '';

    /** Адрес публичной страницы учителя без протокола (для подписи к публикации). */
    private ?string $pageLabel = null;

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

    /** «Поделиться» на компьютере — окно с картинкой; отзыв при этом прочитан. */
    public function share(int $id): void
    {
        $review = $this->own($id);
        $this->service()->markRead($review);
        $this->shareId = $review->id;
        $this->openId = null;
    }

    /** «Поделиться» на телефоне — системное окно без нашего; отзыв прочитан. */
    public function shared(int $id): void
    {
        $this->service()->markRead($this->own($id));
    }

    public function askReport(int $id): void
    {
        $review = $this->own($id);
        if (! $review->is_reported) {
            $this->reportId = $review->id;
            $this->openId = null;
            $this->reset('reportReason', 'reportNote');
            $this->resetValidation(['reportReason', 'reportNote']);
        }
    }

    public function sendReport(): void
    {
        $review = $this->own((int) $this->reportId);

        $this->validate([
            'reportReason' => ['required', Rule::in(array_keys(Review::REPORT_REASONS))],
            'reportNote' => ['nullable', 'required_if:reportReason,other', 'string', 'max:1000'],
        ], [
            'reportReason.required' => 'Выберите причину',
            'reportReason.in' => 'Выберите причину',
            'reportNote.required_if' => 'Опишите коротко, что не так с отзывом',
            'reportNote.max' => 'Сократите описание до 1000 символов',
        ]);

        $this->service()->report($review, auth()->user(), $this->reportReason, $this->reportReason === 'other' ? $this->reportNote : null);
        $this->reportId = null;
        $this->reset('reportReason', 'reportNote');
        $this->dispatch('toast', message: 'Жалоба отправлена');
    }

    /** «Попросить учеников об отзыве»: всем, у кого были занятия и нет отзыва (не чаще раза в 30 дней на ученика). */
    public function requestAll(): void
    {
        $n = app(ReviewPromptService::class)->requestAll(auth()->user());
        $this->dispatch('toast', message: $n > 0
            ? 'Попросили ' . plural_ru($n, 'ученика', 'учеников', 'учеников') . ' оставить отзыв'
            : 'Просить пока некого: у учеников без отзыва ещё не было занятий или их уже просили в этом месяце');
    }

    public function updatedSearch(): void
    {
        $this->limit = self::PAGE;
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
        $searching = filled(trim($this->search));
        $readQuery = fn () => $query()->whereNotNull('teacher_read_at')
            ->when($searching, fn ($q) => $this->service()->searchByStudent($q, $this->search));
        $read = $readQuery()->latest()->orderByDesc('id')->limit($this->limit + 1)->get();
        $readTotal = $readQuery()->count();
        $page = $teacher->username ? route('tutors.show', $teacher->username) : null;
        $this->pageLabel = $page ? preg_replace('#^https?://#', '', $page) : null;

        return view('livewire.cabinet.teacher.reviews', [
            'page' => $page,
            'pageLabel' => $this->pageLabel,
            'fresh' => $fresh->map(fn (Review $r) => $this->row($r)),
            'all' => $read->take($this->limit)->map(fn (Review $r) => $this->row($r)),
            'more' => max(0, $readTotal - $this->limit),
            'searching' => $searching,
            // Поиск по имени — когда прочитанных отзывов больше, чем помещается на экран
            'searchable' => $searching || $this->service()->query($teacher)->whereNotNull('teacher_read_at')->count() > 10,
            'summary' => $this->service()->summary($teacher),
            'opened' => $this->openId ? $this->details($this->own($this->openId)) : null,
            'shared' => $this->shareId ? $this->row($this->own($this->shareId)) : null,
            'reported' => $this->reportId ? $this->row($this->own($this->reportId)) : null,
            'reasons' => Review::REPORT_REASONS,
            'askable' => app(ReviewPromptService::class)->askable($teacher)->count(),
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
            // Подпись к публикации: «текст» — Имя. Все отзывы: serdal.ru/…
            'caption' => '«' . trim((string) $r->text) . '»' . "\n— " . ($r->user?->name ?? 'Ученик') . '.'
                . ($this->pageLabel ? ' Все отзывы: ' . $this->pageLabel : ''),
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
