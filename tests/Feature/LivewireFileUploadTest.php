<?php

namespace Tests\Feature;

use App\Http\Controllers\LivewireFileUploadController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Tests\TestCase;

class LivewireFileUploadTest extends TestCase
{
    /** Длинное русское название: в base64 уходит за 255 байт имени временного файла */
    private const LONG_NAME = 'Занятие 50. Алканы: строение, номенклатура, изомерия, физические и химические свойства.pdf';

    public function test_temporary_file_name_fits_filesystem_limit(): void
    {
        $file = UploadedFile::fake()->create(self::LONG_NAME, 10, 'application/pdf');

        $name = LivewireFileUploadController::temporaryFileName($file);

        $this->assertLessThanOrEqual(LivewireFileUploadController::MAX_FILENAME_BYTES, strlen($name));
        $this->assertStringEndsWith('.pdf', $name);

        // Исходное имя укорочено, но остаётся читаемым и с расширением
        $original = (new TemporaryUploadedFile($name, 'local'))->getClientOriginalName();
        $this->assertStringStartsWith('Занятие 50. Алканы', $original);
        $this->assertStringEndsWith('.pdf', $original);
        $this->assertStringStartsWith(pathinfo($original, PATHINFO_FILENAME), self::LONG_NAME);
    }

    public function test_short_names_are_kept_as_is(): void
    {
        $file = UploadedFile::fake()->create('ДЗ №5.pdf', 10, 'application/pdf');

        $name = LivewireFileUploadController::temporaryFileName($file);

        $this->assertSame('ДЗ №5.pdf', (new TemporaryUploadedFile($name, 'local'))->getClientOriginalName());
    }

    public function test_upload_endpoint_stores_file_with_long_name(): void
    {
        // В тестах Livewire всегда использует диск tmp-for-tests
        Storage::fake('tmp-for-tests');

        $response = $this->post(app(GenerateSignedUploadUrl::class)->forLocal(), [
            'files' => [UploadedFile::fake()->create(self::LONG_NAME, 10, 'application/pdf')],
        ], ['X-Livewire' => '']);

        $response->assertOk();

        $paths = $response->json('paths');

        $this->assertCount(1, $paths);
        $this->assertNotSame('', $paths[0]);
        $this->assertLessThanOrEqual(255, strlen($paths[0]));
        Storage::disk('tmp-for-tests')->assertExists('livewire-tmp/' . $paths[0]);
    }
}
