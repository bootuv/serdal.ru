<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Models\Review;
use App\Rules\NoContacts;
use App\Services\PlatformReviewService;
use App\Support\HumanDate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Отзыв учителя о платформе. Два вида:
 * - prompt — приглашение «Как вам Serdal?» на «Сегодня» (PlatformReviewService::shouldPrompt), его можно закрыть;
 * - по умолчанию — карточка «Отзыв о Serdal» в профиле: оставить, посмотреть статус, изменить.
 * Форма — окно: оценка, текст, согласие показать на сайте. Отзыв появляется на /reviews после проверки.
 */
class PlatformReview extends Component
{
    #[Locked]
    public bool $prompt = false;

    public bool $open = false;

    public int $rating = 5;

    public string $text = '';

    public bool $showOnSite = true;

    /** Приглашение закрыто в этом просмотре (или отзыв уже отправлен). */
    #[Locked]
    public bool $hidden = false;

    public function mount(bool $prompt = false): void
    {
        $this->prompt = $prompt;
    }

    private function service(): PlatformReviewService
    {
        return app(PlatformReviewService::class);
    }

    public function openForm(): void
    {
        $review = $this->service()->forTeacher(auth()->user());
        $this->rating = $review?->rating ?? 5;
        $this->text = (string) ($review?->text ?? '');
        $this->showOnSite = $review?->show_on_site ?? true;
        $this->resetValidation();
        $this->open = true;
    }

    public function closeForm(): void
    {
        $this->open = false;
        $this->resetValidation();
    }

    public function dismiss(): void
    {
        $this->service()->dismissPrompt(auth()->user());
        $this->hidden = true;
    }

    public function save(): void
    {
        $this->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'text' => ['required', 'string', 'min:20', 'max:' . Review::MAX_TEXT, new NoContacts],
        ], [
            'text.required' => 'Напишите хотя бы пару предложений',
            'text.min' => 'Напишите хотя бы пару предложений',
            'text.max' => 'Отзыв длиннее ' . Review::MAX_TEXT . ' символов — сократите его.',
        ]);

        $this->service()->save(auth()->user(), $this->rating, $this->text, $this->showOnSite);

        $this->open = false;
        $this->hidden = $this->prompt;
        $this->dispatch('toast', message: $this->showOnSite ? 'Спасибо! Отзыв появится на сайте после проверки' : 'Спасибо за отзыв!');
    }

    public function render()
    {
        $teacher = auth()->user();
        $review = $this->service()->forTeacher($teacher);

        return view('livewire.cabinet.teacher.platform-review', [
            'visible' => ! $this->prompt || (! $this->hidden && $this->service()->shouldPrompt($teacher)) || $this->open,
            'review' => $review,
            'status' => $review ? PlatformReviewService::status($review) : null,
            'updated' => $review ? HumanDate::date($review->updated_at ?? $review->created_at) : null,
            'stars' => ['', '1 — очень плохо', '2 — плохо', '3 — нормально', '4 — хорошо', '5 — отлично'],
            'publicUrl' => route('reviews'),
        ]);
    }
}
