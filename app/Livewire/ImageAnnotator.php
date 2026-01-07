<?php

namespace App\Livewire;

use App\Models\HomeworkActivity;
use App\Models\HomeworkSubmission;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class ImageAnnotator extends Component
{
    public string $imageUrl = '';
    public string $imagePath = '';
    public ?int $submissionId = null;
    public bool $showModal = false;

    protected $listeners = ['openAnnotator'];

    public function openAnnotator(string $imagePath, ?int $submissionId = null): void
    {
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

        if (empty($imageData)) {
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
        return view('livewire.image-annotator');
    }
}
