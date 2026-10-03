<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Student\Home;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Аватар в кабинетах: загружено фото — фото, нет — инициалы. */
class AvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('s3');
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

    public function test_avatar_shows_photo_instead_of_initials(): void
    {
        $withPhoto = $this->user(User::ROLE_TUTOR, ['name' => 'Азиева Айна', 'avatar' => 'avatars/1/abc.webp']);
        $withoutPhoto = $this->user(User::ROLE_TUTOR, ['name' => 'Азиева Айна']);

        $html = Blade::render('<x-ui.avatar :user="$u" />', ['u' => $withPhoto]);
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('avatars/1/abc-256.webp', $html);
        $this->assertStringNotContainsString('АА', $html);

        $this->assertStringContainsString('АА', Blade::render('<x-ui.avatar :user="$u" />', ['u' => $withoutPhoto]));
        // Фото удалили, но ещё не сохранили — инициалы
        $this->assertStringContainsString('АА', Blade::render('<x-ui.avatar :user="$u" :photo="false" />', ['u' => $withPhoto]));
        // Человек передан именем и адресом фото
        $html = Blade::render('<x-ui.avatar name="Азиева Айна" :id="1" photo="https://cdn.test/p.webp" />');
        $this->assertStringContainsString('https://cdn.test/p.webp', $html);
    }

    public function test_cabinet_screens_show_photos(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, ['name' => 'Азиева Айна', 'avatar' => 'avatars/7/teacher.webp']);
        $student = $this->user(User::ROLE_STUDENT, ['avatar' => 'avatars/8/student.webp']);
        $teacher->students()->attach($student->id);

        // «Ваш учитель» на главной ученика и аватар самого ученика в меню
        Livewire::actingAs($student)->test(Home::class)
            ->assertSee('avatars/7/teacher-256.webp', false);
        $this->actingAs($student)->get(route('cabinet.student.home'))
            ->assertSee('avatars/8/student-256.webp', false);
    }
}
