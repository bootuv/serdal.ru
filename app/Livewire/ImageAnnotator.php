<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class ImageAnnotator extends Component
{
    public string $imageUrl = '';
    public string $imagePath = '';
    public ?string $annotatedImagePath = null;
    public bool $showModal = false;

    protected $listeners = ['openAnnotator'];

    public function openAnnotator(string $imagePath): void
    {
        $this->imagePath = $imagePath;

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

        // Generate unique filename
        $extension = 'png';
        $filename = 'homework-feedback/annotated_' . uniqid() . '.' . $extension;

        // Save to S3
        Storage::disk('s3')->put($filename, $imageData, 'public');

        $this->annotatedImagePath = $filename;
        $this->showModal = false;

        // Dispatch event to parent component
        $this->dispatch('imageAnnotated', path: $filename);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->imageUrl = '';
        $this->imagePath = '';
    }

    public function render()
    {
        return view('livewire.image-annotator');
    }
}
