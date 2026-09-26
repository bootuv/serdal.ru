<?php

namespace App\Livewire;

use App\Models\HomeworkActivity;
use App\Models\HomeworkSubmission;
use App\Services\HomeworkSubmissionService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Пометки учителя на фото из работы ученика: рисует поверх фото и сохраняет его на место оригинала.
 * Старый кабинет (Filament): полноэкранное окно, открывается событием openAnnotator.
 * Новый кабинет: embedded — только холст и инструменты внутри окна родителя (x-ui.modal),
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
    #[Locked]
    public bool $embedded = false;

    protected $listeners = ['openAnnotator'];

    public function mount(bool $embedded = false, ?string $imagePath = null, ?int $submissionId = null): void
    {
        $this->embedded = $embedded;

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

        // Generate temporary URL for S3 image
        try {
            $this->imageUrl = Storage::disk('s3')->temporaryUrl($imagePath, now()->addMinutes(30));
        } catch (\Exception $e) {
            $this->imageUrl = Storage::url($imagePath);
        }

        $this->showModal = true;
    }

    public function saveAnnotatedImage(string $dataUrl): void
    {
        // Extract base64 data from data URL
        $data = explode(',', $dataUrl);
        $imageData = base64_decode($data[1] ?? '');

        if (empty($imageData) || ! $this->allowed($this->imagePath, $this->submissionId)) {
            return;
        }

        // Replace original file in S3 (same path)
        Storage::disk('s3')->put($this->imagePath, $imageData, 'public');

        // Track annotation in submission and log activity
        if ($this->submissionId) {
            $submission = HomeworkSubmission::find($this->submissionId);
            if ($submission) {
                $annotatedFiles = $submission->annotated_files ?? [];
                if (!in_array($this->imagePath, $annotatedFiles)) {
                    $annotatedFiles[] = $this->imagePath;
                    $submission->update(['annotated_files' => $annotatedFiles]);
                }

                // Log annotation activity
                HomeworkActivity::log(
                    $submission->id,
                    HomeworkActivity::TYPE_ANNOTATED,
                    auth()->id(),
                    ['filename' => basename($this->imagePath)]
                );
            }
        }

        $this->showModal = false;

        // Dispatch event with same path (file replaced in-place)
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
        return view($this->embedded ? 'livewire.cabinet.image-annotator' : 'livewire.image-annotator');
    }
}
