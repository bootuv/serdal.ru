<?php

namespace Tests\Feature\Cabinet;

use App\Http\Responses\LoginResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** После входа ученик и учитель попадают в новые кабинеты, админ — в /admin. */
class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function landing(string $role): string
    {
        $user = User::factory()->create(['role' => $role, 'username' => $role . uniqid(), 'is_active' => true, 'is_blocked' => false]);
        $this->actingAs($user);

        return (new LoginResponse)->toResponse(request())->getTargetUrl();
    }

    public function test_landing_by_role(): void
    {
        $this->assertSame(route('cabinet.student.home'), $this->landing(User::ROLE_STUDENT));
        $this->assertSame(route('cabinet.teacher.today'), $this->landing(User::ROLE_TUTOR));
        $this->assertSame(url('/admin'), $this->landing(User::ROLE_ADMIN));
    }

    public function test_intended_url_is_kept(): void
    {
        session()->put('url.intended', url('/cabinet/teacher/students'));

        $this->assertSame(url('/cabinet/teacher/students'), $this->landing(User::ROLE_TUTOR));
    }
}
