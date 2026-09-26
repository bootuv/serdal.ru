<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Student\Materials;
use App\Models\MaterialFolder;
use App\Models\TeacherMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Материалы ученика в новом кабинете (/cabinet/student/materials). */
class StudentMaterialsTest extends TestCase
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

    public function test_guest_is_redirected_and_teacher_is_forbidden(): void
    {
        $this->get(route('cabinet.student.materials'))->assertRedirect();

        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->get(route('cabinet.student.materials'))
            ->assertRedirect(route('cabinet.teacher.today'));
    }

    public function test_student_without_materials_sees_empty_state(): void
    {
        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.student.materials'))
            ->assertOk()
            ->assertSee('Материалов пока нет');
    }

    public function test_student_sees_only_materials_open_to_him(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $stranger = $this->user(User::ROLE_TUTOR, 'Чужой Учитель');
        $student = $this->user(User::ROLE_STUDENT);
        $teacher->students()->attach($student->id);

        $folder = MaterialFolder::create(['teacher_id' => $teacher->id, 'name' => 'Грамматика']);
        $this->material($teacher, 'Present Perfect', ['folder_id' => $folder->id]);
        $this->material($teacher, 'Слова к разделу 3');
        $this->material($teacher, 'Черновик учителя', ['visibility' => TeacherMaterial::VISIBILITY_PRIVATE]);
        $this->material($stranger, 'Чужой файл');

        $this->actingAs($student)
            ->get(route('cabinet.student.materials'))
            ->assertOk()
            ->assertSee('Новое за неделю')
            ->assertSee('Грамматика')
            ->assertSee('Слова к разделу 3')
            ->assertSee(route('cabinet.student.materials', ['folder' => $folder->id]), false)
            ->assertDontSee('Черновик учителя')
            ->assertDontSee('Чужой файл');

        // Папка открывается: внутри файл, назад — к материалам
        $this->actingAs($student)
            ->get(route('cabinet.student.materials', ['folder' => $folder->id]))
            ->assertOk()
            ->assertSee('Present Perfect')
            ->assertDontSee('Слова к разделу 3');
    }

    public function test_foreign_folder_and_teacher_are_not_found(): void
    {
        $stranger = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $folder = MaterialFolder::create(['teacher_id' => $stranger->id, 'name' => 'Чужая папка']);
        $this->material($stranger, 'Секрет', ['folder_id' => $folder->id]);

        $this->actingAs($student)
            ->get(route('cabinet.student.materials', ['folder' => $folder->id]))
            ->assertNotFound();

        $this->actingAs($student)
            ->get(route('cabinet.student.materials', ['teacher' => $stranger->id]))
            ->assertNotFound();
    }

    public function test_search_and_teacher_filter(): void
    {
        $maria = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $ivan = $this->user(User::ROLE_TUTOR, 'Иван Орлов');
        $student = $this->user(User::ROLE_STUDENT);
        $maria->students()->attach($student->id);
        $ivan->students()->attach($student->id);

        $this->material($maria, 'Present Perfect');
        $this->material($ivan, 'Таблица квадратов');

        Livewire::actingAs($student)
            ->test(Materials::class)
            ->assertSee('Все материалы')
            ->assertSee('Present Perfect')
            ->assertSee('Таблица квадратов')
            ->set('teacher', (string) $ivan->id)
            ->assertSee('Таблица квадратов')
            ->assertDontSee('Present Perfect')
            ->set('teacher', 'all')
            ->set('search', 'квадрат')
            ->assertSee('Найдено')
            ->assertSee('Таблица квадратов')
            ->assertDontSee('Present Perfect')
            ->set('search', 'нет такого')
            ->assertSee('Ничего не нашлось');
    }
}
