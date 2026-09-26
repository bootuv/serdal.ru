<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\Materials;
use App\Models\MaterialFolder;
use App\Models\Room;
use App\Models\TeacherMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Материалы учителя в новом кабинете (/cabinet/teacher/materials). */
class TeacherMaterialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('s3');
    }

    private function user(string $role, ?string $name = null): User
    {
        return User::factory()->create(array_filter([
            'name' => $name,
            'role' => $role,
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]));
    }

    private function material(User $teacher, string $title, array $attrs = []): TeacherMaterial
    {
        $path = 'materials/' . uniqid() . '.pdf';
        Storage::disk('s3')->put($path, 'pdf');

        return TeacherMaterial::create(array_merge([
            'teacher_id' => $teacher->id,
            'title' => $title,
            'file_path' => $path,
            'original_name' => $title . '.pdf',
            'visibility' => TeacherMaterial::VISIBILITY_ALL,
        ], $attrs));
    }

    private function room(User $teacher, array $students, string $name, string $type = 'individual'): Room
    {
        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => $name,
            'type' => $type,
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
        ]);
        foreach ($students as $s) {
            $room->participants()->attach($s->id);
            $teacher->students()->syncWithoutDetaching([$s->id]);
        }

        return $room;
    }

    public function test_access(): void
    {
        $this->get(route('cabinet.teacher.materials'))->assertRedirect();

        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.teacher.materials'))
            ->assertRedirect(route('cabinet.student.home'));

        // Чужая папка — 404
        $stranger = $this->user(User::ROLE_TUTOR);
        $folder = MaterialFolder::create(['teacher_id' => $stranger->id, 'name' => 'Чужая']);
        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->get(route('cabinet.teacher.materials', ['folder' => $folder->id]))
            ->assertNotFound();
    }

    public function test_library_shows_own_folders_files_and_access(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $alina = $this->user(User::ROLE_STUDENT, 'Алина Смирнова');
        $room = $this->room($teacher, [$alina], 'Английский');
        $group = $this->room($teacher, [$this->user(User::ROLE_STUDENT), $this->user(User::ROLE_STUDENT)], 'ЕГЭ-2027', 'group');

        $ege = MaterialFolder::create(['teacher_id' => $teacher->id, 'name' => 'ЕГЭ-2027']);
        MaterialFolder::create(['teacher_id' => $teacher->id, 'parent_id' => $ege->id, 'name' => 'Теория']);
        $this->material($teacher, 'Вариант 12', ['folder_id' => $ege->id, 'visibility' => TeacherMaterial::VISIBILITY_ROOMS])->rooms()->attach($group->id);
        $this->material($teacher, 'Present Perfect', ['visibility' => TeacherMaterial::VISIBILITY_ROOMS])->rooms()->attach($room->id);
        $this->material($teacher, 'План на октябрь', ['visibility' => TeacherMaterial::VISIBILITY_PRIVATE]);
        $this->material($teacher, 'Окружность');
        $this->material($this->user(User::ROLE_TUTOR), 'Чужой файл');

        $this->actingAs($teacher)
            ->get(route('cabinet.teacher.materials'))
            ->assertOk()
            ->assertSee('4 файла в 2 папках')
            ->assertSee('Все материалы')
            ->assertSee('ЕГЭ-2027')
            ->assertSee('1 папка · 1 файл')
            ->assertSee('Present Perfect')
            ->assertSee('Видно: Алина Смирнова')
            ->assertSee('Только вам')
            ->assertSee('Видно всем ученикам')
            ->assertSee('Кому открыто')
            ->assertSee('Группа «ЕГЭ-2027»')
            ->assertDontSee('Вариант 12')
            ->assertDontSee('Чужой файл');

        // Внутри папки и поиск по всему каталогу
        $this->actingAs($teacher)
            ->get(route('cabinet.teacher.materials', ['folder' => $ege->id]))
            ->assertOk()
            ->assertSee('Вариант 12')
            ->assertSee('Видно: группа «ЕГЭ-2027»')
            ->assertDontSee('Present Perfect');

        Livewire::actingAs($teacher)->test(Materials::class)
            ->set('search', 'Вариант')
            ->assertSee('Вариант 12')
            ->assertSee('ЕГЭ-2027 · Видно')
            ->assertDontSee('Окружность')
            ->set('search', 'нет такого')
            ->assertSee('Ничего не нашлось');
    }

    public function test_folders_create_rename_move_and_delete(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $a = MaterialFolder::create(['teacher_id' => $teacher->id, 'name' => 'A']);
        $b = MaterialFolder::create(['teacher_id' => $teacher->id, 'parent_id' => $a->id, 'name' => 'B']);
        $file = $this->material($teacher, 'Файл в B', ['folder_id' => $b->id]);

        Livewire::actingAs($teacher)->test(Materials::class, ['folder' => $a->id])
            ->call('newFolder')
            ->set('folderName', 'Новая')
            ->call('saveFolder')
            ->assertDispatched('toast');
        $this->assertDatabaseHas('material_folders', ['name' => 'Новая', 'parent_id' => $a->id, 'teacher_id' => $teacher->id]);

        // Папку нельзя положить в собственную подпапку
        Livewire::actingAs($teacher)->test(Materials::class, ['folder' => $a->id])
            ->call('editFolder')
            ->set('folderName', 'A2')
            ->set('folderParent', (string) $b->id)
            ->call('saveFolder')
            ->assertHasErrors('folderParent');

        Livewire::actingAs($teacher)->test(Materials::class, ['folder' => $b->id])
            ->call('editFolder')
            ->set('folderName', 'B2')
            ->set('folderParent', '')
            ->call('saveFolder');
        $this->assertDatabaseHas('material_folders', ['id' => $b->id, 'name' => 'B2', 'parent_id' => null]);

        // Удаление: файлы поднимаются на уровень выше
        Livewire::actingAs($teacher)->test(Materials::class, ['folder' => $b->id])
            ->call('editFolder')
            ->set('confirm', 'folder')
            ->call('deleteFolder')
            ->assertSet('folder', null);
        $this->assertDatabaseMissing('material_folders', ['id' => $b->id]);
        $this->assertNull($file->fresh()->folder_id);
    }

    public function test_file_access_move_and_delete(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $alina = $this->user(User::ROLE_STUDENT, 'Алина Смирнова');
        $room = $this->room($teacher, [$alina], 'Английский');
        $foreignRoom = $this->room($this->user(User::ROLE_TUTOR), [], 'Чужое');
        $folder = MaterialFolder::create(['teacher_id' => $teacher->id, 'name' => 'Папка']);
        $file = $this->material($teacher, 'Черновик', ['visibility' => TeacherMaterial::VISIBILITY_PRIVATE]);
        $foreign = $this->material($this->user(User::ROLE_TUTOR), 'Чужой');

        Livewire::actingAs($teacher)->test(Materials::class)
            ->call('editFile', $file->id)
            ->set('fileTitle', 'Present Perfect')
            ->set('fileVisibility', TeacherMaterial::VISIBILITY_ROOMS)
            ->call('saveFile')
            ->assertHasErrors('fileRooms')
            ->set('fileRooms', [(string) $room->id, (string) $foreignRoom->id])
            ->set('fileFolder', (string) $folder->id)
            ->call('saveFile')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $file->refresh();
        $this->assertSame('Present Perfect', $file->title);
        $this->assertSame(TeacherMaterial::VISIBILITY_ROOMS, $file->visibility);
        $this->assertSame([$room->id], $file->rooms()->pluck('rooms.id')->all());
        $this->assertSame($folder->id, (int) $file->folder_id);
        $this->assertTrue($file->isVisibleTo($alina));

        // Перетаскивание в корень
        Livewire::actingAs($teacher)->test(Materials::class)->call('moveMaterial', $file->id, null);
        $this->assertNull($file->fresh()->folder_id);

        // Чужой файл не открыть
        Livewire::actingAs($teacher)->test(Materials::class)
            ->call('editFile', $foreign->id)
            ->assertNotFound();

        Livewire::actingAs($teacher)->test(Materials::class)
            ->call('editFile', $file->id)
            ->set('confirm', 'file')
            ->call('deleteFile');
        $this->assertDatabaseMissing('teacher_materials', ['id' => $file->id]);
        $this->assertDatabaseHas('teacher_materials', ['id' => $foreign->id]);
    }

    public function test_upload_stores_file_with_access(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $folder = MaterialFolder::create(['teacher_id' => $teacher->id, 'name' => 'Папка']);

        Livewire::actingAs($teacher)->test(Materials::class, ['folder' => $folder->id])
            ->call('openUpload')
            ->assertSet('uploadFolder', (string) $folder->id)
            ->set('uploads', [UploadedFile::fake()->create('Вариант ЕГЭ.pdf', 100, 'application/pdf')])
            ->set('uploadVisibility', TeacherMaterial::VISIBILITY_PRIVATE)
            ->call('saveUpload')
            ->assertSet('uploadOpen', false)
            ->assertDispatched('toast');

        $material = TeacherMaterial::where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($material);
        $this->assertSame('Вариант ЕГЭ', $material->title);
        $this->assertSame($folder->id, (int) $material->folder_id);
        $this->assertSame(TeacherMaterial::VISIBILITY_PRIVATE, $material->visibility);
        Storage::disk('s3')->assertExists($material->file_path);
    }

    public function test_select_several_move_and_delete_only_own(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $folder = MaterialFolder::create(['teacher_id' => $teacher->id, 'name' => 'Архив']);
        $foreignFolder = MaterialFolder::create(['teacher_id' => $this->user(User::ROLE_TUTOR)->id, 'name' => 'Чужая']);
        $a = $this->material($teacher, 'Вариант 1');
        $b = $this->material($teacher, 'Вариант 2');
        $keep = $this->material($teacher, 'Вариант 3');
        $foreign = $this->material($this->user(User::ROLE_TUTOR), 'Чужой файл');

        $component = Livewire::actingAs($teacher)->test(Materials::class)
            ->call('startSelect')
            ->assertSee('Выберите файлы')
            ->call('toggle', $a->id)
            ->call('toggle', $b->id)
            ->call('toggle', $foreign->id)
            ->assertSet('picked', [$a->id, $b->id])
            ->assertSee('Выбрано: 2')
            // В чужую папку не перемещается
            ->call('askBulk', 'move')
            ->assertSee('Переместить 2 файла')
            ->set('bulkFolder', (string) $foreignFolder->id)
            ->call('moveSelected');
        $this->assertNull($a->fresh()->folder_id);

        $component->call('startSelect')
            ->call('toggle', $a->id)
            ->call('toggle', $b->id)
            ->call('askBulk', 'move')
            ->set('bulkFolder', (string) $folder->id)
            ->call('moveSelected')
            ->assertSet('selecting', false)
            ->assertDispatched('toast', message: 'Перемещено в «Архив»: 2 файла');
        $this->assertSame($folder->id, (int) $a->fresh()->folder_id);
        $this->assertSame($folder->id, (int) $b->fresh()->folder_id);

        // Массовое удаление: чужой id, подложенный в выбор, не удаляется
        $component->call('startSelect')
            ->set('picked', [$a->id, $b->id, $foreign->id])
            ->call('askBulk', 'delete')
            ->assertSee('Удалить 3 файла?')
            ->call('deleteSelected')
            ->assertDispatched('toast', message: 'Удалено: 2 файла');

        $this->assertDatabaseMissing('teacher_materials', ['id' => $a->id]);
        $this->assertDatabaseMissing('teacher_materials', ['id' => $b->id]);
        $this->assertDatabaseHas('teacher_materials', ['id' => $keep->id]);
        $this->assertDatabaseHas('teacher_materials', ['id' => $foreign->id]);
        Storage::disk('s3')->assertExists($foreign->file_path);
        Storage::disk('s3')->assertMissing($a->file_path);
    }

    public function test_manual_order_by_dragging(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $first = $this->material($teacher, 'Первый');
        $second = $this->material($teacher, 'Второй');
        $third = $this->material($teacher, 'Третий');
        foreach ([$first, $second, $third] as $i => $m) {
            $m->forceFill(['created_at' => now()->subMinutes(3 - $i)])->saveQuietly();
        }
        $foreign = $this->material($this->user(User::ROLE_TUTOR), 'Чужой');

        // Новые сверху: Третий, Второй, Первый → «Первый» перед «Третьим»
        $component = Livewire::actingAs($teacher)->test(Materials::class);
        $this->assertOrder($component->html(), ['Третий', 'Второй', 'Первый']);
        $component->call('reorderMaterial', $first->id, $third->id, true);
        $this->assertOrder($component->html(), ['Первый', 'Третий', 'Второй']);
        // После «Второго» — в конец
        $component->call('reorderMaterial', $first->id, $second->id, false);
        $this->assertOrder($component->html(), ['Третий', 'Второй', 'Первый']);
        // Чужой файл порядок не трогает
        $component->call('reorderMaterial', $foreign->id, $third->id, true);
        $this->assertSame(0, (int) $foreign->fresh()->sort_order);

        $algebra = MaterialFolder::create(['teacher_id' => $teacher->id, 'name' => 'Алгебра']);
        $geometry = MaterialFolder::create(['teacher_id' => $teacher->id, 'name' => 'Геометрия']);
        $component = Livewire::actingAs($teacher)->test(Materials::class);
        $this->assertOrder($component->html(), ['Алгебра', 'Геометрия']);
        $component->call('reorderFolder', $geometry->id, $algebra->id, true);
        $this->assertOrder($component->html(), ['Геометрия', 'Алгебра']);
    }

    private function assertOrder(string $html, array $texts): void
    {
        $positions = array_map(fn ($t) => mb_strpos($html, $t), $texts);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'Порядок: ' . implode(', ', $texts));
    }

    public function test_description_and_file_replacement(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $file = $this->material($teacher, 'Вариант 12');
        $oldPath = $file->file_path;

        Livewire::actingAs($teacher)->test(Materials::class)
            ->call('editFile', $file->id)
            ->assertSee('Описание')
            ->assertSee('Заменить')
            ->set('fileDescription', 'Для подготовки к контрольной')
            ->set('replacement', UploadedFile::fake()->create('Вариант 12 исправленный.pdf', 50, 'application/pdf'))
            ->set('replacementName', 'Вариант 12 исправленный.pdf')
            ->assertHasNoErrors()
            ->assertSee('Заменит текущий файл после сохранения')
            ->call('saveFile')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Файл заменён');

        $file->refresh();
        $this->assertSame('Для подготовки к контрольной', $file->description);
        $this->assertSame('Вариант 12', $file->title);
        $this->assertSame('Вариант 12 исправленный.pdf', $file->original_name);
        $this->assertNotSame($oldPath, $file->file_path);
        Storage::disk('s3')->assertExists($file->file_path);
        Storage::disk('s3')->assertMissing($oldPath);

        // Слишком большой файл — ошибка, прежний файл остаётся
        Livewire::actingAs($teacher)->test(Materials::class)
            ->call('editFile', $file->id)
            ->set('replacement', UploadedFile::fake()->create('big.pdf', \App\Services\TeacherMaterialsService::MAX_FILE_KB + 1, 'application/pdf'))
            ->assertHasErrors(['replacement'])
            ->assertSet('replacement', null);
    }
}
