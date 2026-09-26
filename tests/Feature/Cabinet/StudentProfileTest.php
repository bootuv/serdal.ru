<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Student\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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
            ->assertDontSee('Оставить отзыв');

        Livewire::actingAs($student)->test(Profile::class)->assertSet('grade', '10');
    }

    public function test_student_saves_profile_password_and_photo(): void
    {
        Storage::fake('s3');
        $student = $this->user(User::ROLE_STUDENT);

        Livewire::actingAs($student)
            ->test(Profile::class)
            ->set('last_name', 'Смирнова')
            ->set('first_name', 'Алина')
            ->set('email', 'alina@example.com')
            ->set('phone', '+7 916 555-12-34')
            ->set('grade', '9')
            ->set('password', 'new-secret')
            ->set('photo', UploadedFile::fake()->image('me.jpg', 1200, 1200))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true)
            ->assertSet('password', '')
            ->assertSee('Изменения сохранены');

        $student->refresh();
        $this->assertSame('Смирнова Алина', $student->name);
        $this->assertSame('Алина', $student->first_name);
        $this->assertSame('alina@example.com', $student->email);
        $this->assertSame([9], $student->grade);
        $this->assertTrue(Hash::check('new-secret', $student->password));
        $this->assertStringStartsWith('avatars/' . $student->id . '/', $student->avatar);
        Storage::disk('s3')->assertExists($student->avatar);
    }

    public function test_profile_validation(): void
    {
        $this->user(User::ROLE_STUDENT, ['email' => 'taken@example.com']);
        $student = $this->user(User::ROLE_STUDENT);

        Livewire::actingAs($student)
            ->test(Profile::class)
            ->set('first_name', '')
            ->set('email', 'taken@example.com')
            ->set('phone', 'позвоните мне')
            ->set('grade', '42')
            ->call('save')
            ->assertHasErrors(['first_name', 'email', 'phone', 'grade'])
            ->assertSet('saved', false);

        Livewire::actingAs($student)
            ->test(Profile::class)
            ->set('photo', UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'))
            ->assertHasErrors(['photo']);
    }

    public function test_new_password_needs_eight_characters_and_empty_keeps_old(): void
    {
        $student = $this->user(User::ROLE_STUDENT, ['first_name' => 'Алина', 'last_name' => 'Смирнова', 'password' => Hash::make('old-secret')]);

        Livewire::actingAs($student)
            ->test(Profile::class)
            ->set('password', 'short')
            ->call('save')
            ->assertHasErrors(['password' => 'min'])
            ->assertSee('Пароль — минимум 8 символов')
            ->assertSet('saved', false)
            // Пустое поле — пароль не меняем
            ->set('password', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true);

        $this->assertTrue(Hash::check('old-secret', $student->fresh()->password));
    }
}
