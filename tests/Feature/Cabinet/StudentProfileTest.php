<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Student\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Профиль ученика в новом кабинете (/cabinet/student/profile). */
class StudentProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ], $attrs));
    }

    public function test_guest_is_redirected_and_teacher_is_forbidden(): void
    {
        $this->get(route('cabinet.student.profile'))->assertRedirect();

        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->get(route('cabinet.student.profile'))
            ->assertRedirect(route('cabinet.teacher.today'));
    }

    public function test_profile_page_renders_form_and_logout(): void
    {
        $student = $this->user(User::ROLE_STUDENT, ['name' => 'Алина Смирнова', 'grade' => [10]]);

        $this->actingAs($student)
            ->get(route('cabinet.student.profile'))
            ->assertOk()
            ->assertSee('Личные данные')
            ->assertSee('Алина Смирнова')
            ->assertSee('Уведомления')
            ->assertSee(route('logout'), false)
            ->assertSee('Выйти')
            // Почта и пароль меняются на отдельной странице
            ->assertSee(route('cabinet.account'), false)
            ->assertDontSee('Новый пароль')
            ->assertDontSee('Оставить отзыв');

        Livewire::actingAs($student)->test(Profile::class)->assertSet('grade', '10');
    }

    public function test_student_saves_profile_and_photo(): void
    {
        Storage::fake('s3');
        $student = $this->user(User::ROLE_STUDENT);

        Livewire::actingAs($student)
            ->test(Profile::class)
            ->set('last_name', 'Смирнова')
            ->set('first_name', 'Алина')
            ->set('phone', '+7 916 555-12-34')
            ->set('grade', '9')
            ->set('photo', UploadedFile::fake()->image('me.jpg', 1200, 1200))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true)
            ->assertSee('Изменения сохранены');

        $student->refresh();
        $this->assertSame('Смирнова Алина', $student->name);
        $this->assertSame('Алина', $student->first_name);
        $this->assertSame([9], $student->grade);
        $this->assertStringStartsWith('avatars/' . $student->id . '/', $student->avatar);
        Storage::disk('s3')->assertExists($student->avatar);
    }

    public function test_student_removes_photo_and_sees_crop_window(): void
    {
        Storage::fake('s3');
        $student = $this->user(User::ROLE_STUDENT, ['avatar' => 'avatars/1/old.webp']);
        Storage::disk('s3')->put('avatars/1/old.webp', 'webp');

        Livewire::actingAs($student)
            ->test(Profile::class)
            // Фото выбирают через окно обрезки (x-ui.photo-crop), а не отправляют файл как есть
            ->assertSeeHtml('x-data="photoCrop(\'photo\')"')
            ->assertSee('Удалить')
            ->call('deletePhoto')
            ->assertSet('removePhoto', true)
            ->assertDontSee('Удалить')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('removePhoto', false);

        $this->assertNull($student->fresh()->avatar);
        Storage::disk('s3')->assertMissing('avatars/1/old.webp');
    }

    public function test_profile_validation(): void
    {
        $student = $this->user(User::ROLE_STUDENT);

        Livewire::actingAs($student)
            ->test(Profile::class)
            ->set('first_name', '')
            ->set('phone', 'позвоните мне')
            ->set('grade', '42')
            ->call('save')
            ->assertHasErrors(['first_name', 'phone', 'grade'])
            ->assertSet('saved', false);

        Livewire::actingAs($student)
            ->test(Profile::class)
            ->set('photo', UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'))
            ->assertHasErrors(['photo']);
    }
}
