<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\CabinetUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Старая Filament-админка удалена: адреса /admin/… и ссылки старых уведомлений ведут в новую админку. */
class LegacyAdminTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);
    }

    public function test_old_admin_addresses_redirect_to_new_screens(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $teacher = $this->user(User::ROLE_TUTOR);

        $this->actingAs($admin);
        $this->get('/admin')->assertRedirect(route('cabinet.admin.today'));
        $this->get('/admin/teacher-applications')->assertRedirect(route('cabinet.admin.applications'));
        $this->get('/admin/admin-messenger?chat=7')->assertRedirect(route('cabinet.admin.support', ['chat' => 7]));
        $this->get('/admin/users/' . $teacher->id . '/edit')->assertRedirect(route('cabinet.admin.user', ['user' => $teacher->id]));
        $this->get('/admin/help-articles/create')->assertRedirect(route('cabinet.admin.help-article', ['article' => 'new']));
        $this->get('/admin/directs')->assertRedirect(route('cabinet.admin.settings', ['tab' => 'dictionaries', 'dict' => 'directs']));
        $this->get('/admin/meeting-sessions/999')->assertRedirect(route('cabinet.admin.lessons', ['tab' => 'deletions']));
        $this->get('/admin/something-unknown')->assertRedirect(route('cabinet.admin.today'));
    }

    public function test_guest_goes_to_login_and_others_to_their_cabinet(): void
    {
        $this->get('/admin/users')->assertRedirect(route('login'));
        $this->get('/admin/login')->assertRedirect(route('login'));

        $this->actingAs($this->user(User::ROLE_TUTOR))->get('/admin/users')->assertRedirect(route('cabinet.teacher.today'));
        $this->actingAs($this->user(User::ROLE_STUDENT))->get('/admin')->assertRedirect(route('cabinet.student.home'));
    }

    public function test_old_notification_links_are_translated_only_for_admin(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $teacher = $this->user(User::ROLE_TUTOR);

        $this->assertSame(route('cabinet.admin.reviews'), CabinetUrl::fromLegacy(url('/admin/reviews/5/edit'), $admin));
        $this->assertSame(route('cabinet.admin.users'), CabinetUrl::fromLegacy(url('/admin/users?tableFilters[role][value]=tutor'), $admin));
        // Не админу ссылку не переводим — переадресация сама отправит в свой кабинет
        $this->assertSame(url('/admin/reviews'), CabinetUrl::fromLegacy(url('/admin/reviews'), $teacher));
    }
}
