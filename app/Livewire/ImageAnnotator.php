<?php

namespace App\Livewire;

use App\Models\HomeworkSubmission;
use App\Services\HomeworkSubmissionService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Пометки учителя на фото из работы ученика. Оригинал фото не трогаем: пометки сохраняются отдельным файлом
 * (HomeworkSubmissionService::saveAnnotation), повторно открытое фото показывается уже с пометками.
 * Холст и инструменты — внутри окна родителя (x-ui.modal, экран проверки работы),
 * сохранение — браузерным событием annotator-save; после сохранения — событие imageAnnotated.
 */
class ImageAnnotator extends Component
{
    public string $imageUrl = '';
    #[Locked]
    public string $imagePath = '';
    #[Locked]
    public ?int $submissionId = null;
    public bool $showModal = false;

    public function mount(?string $imagePath = null, ?int $submissionId = null): void
    {
        if ($imagePath !== null) {
            $this->openAnnotator($imagePath, $submissionId);
        }
    }

    /** Размечать можно только фото из сданной работы по своему заданию. */
    private function allowed(string $imagePath, ?int $submissionId): bool
    {
        $submission = $submissionId ? HomeworkSubmission::with('homework:id,teacher_id')->find($submissionId) : null;

        return $submission
            && HomeworkSubmissionService::canReview(auth()->user(), $submission)
            && in_array($imagePath, $submission->attachments ?? [], true);
    }

    public function openAnnotator(string $imagePath, ?int $submissionId = null): void
    {
        if (! $this->allowed($imagePath, $submissionId)) {
            return;
        }

        $this->imagePath = $imagePath;
        $this->submissionId = $submissionId;

        // Уже есть пометки — продолжаем рисовать поверх них
        $shown = HomeworkSubmission::find($submissionId)?->markedFiles()[$imagePath] ?? $imagePath;

        try {
            $this->imageUrl = Storage::disk('s3')->temporaryUrl($shown, now()->addMinutes(30));
        } catch (\Exception $e) {
            $this->imageUrl = Storage::url($shown);
        }

        $this->showModal = true;
    }

    public function saveAnnotatedImage(string $dataUrl): void
    {
        $data = explode(',', $dataUrl);
        $imageData = base64_decode($data[1] ?? '');

        if (empty($imageData) || ! $this->allowed($this->imagePath, $this->submissionId)) {
            return;
        }

        app(HomeworkSubmissionService::class)->saveAnnotation(
            HomeworkSubmission::with('homework:id,teacher_id')->findOrFail($this->submissionId),
            $this->imagePath,
            $imageData,
        );

        $this->showModal = false;

        // path — фото ученика (как раньше), родитель обновляет список
        $this->dispatch('imageAnnotated', path: $this->imagePath);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->imageUrl = '';
        $this->imagePath = '';
        $this->submissionId = null;
    }

    public function render()
    {
        return view('livewire.cabinet.image-annotator');
    }
}
