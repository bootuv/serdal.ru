<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

/** Вход, восстановление и сброс пароля (/login, /forgot-password, /reset-password/{token}). */
class AuthScreensTest extends TestCase
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
            'email' => $role . '@mail.ru',
            'password' => Hash::make('sunnyday2026'),
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ], $attrs));
    }

    public function test_login_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Вход в кабинет')
            ->assertSee('Забыли пароль?')
            ->assertSee('Стать репетитором')
            ->assertSee(asset('images/bg.jpg'), false);

        // Уже вошедший — сразу в свой кабинет
        $this->actingAs($this->user(User::ROLE_TUTOR))->get('/login')->assertRedirect(route('cabinet.teacher.today'));
    }

    public function test_login_by_role(): void
    {
        $this->user(User::ROLE_STUDENT);
        Livewire::test(Login::class)
            ->set('email', 'student@mail.ru')
            ->set('password', 'sunnyday2026')
            ->call('login')
            ->assertRedirect(route('cabinet.student.home'));
        $this->assertAuthenticated();
    }

    public function test_admin_logs_in_here_too(): void
    {
        $this->user(User::ROLE_ADMIN);
        Livewire::test(Login::class)
            ->set('email', 'admin@mail.ru')
            ->set('password', 'sunnyday2026')
            ->call('login')
            ->assertRedirect(url('/admin'));
    }

    public function test_wrong_password_and_throttle(): void
    {
        $this->user(User::ROLE_TUTOR);
        $c = Livewire::test(Login::class)->set('email', 'tutor@mail.ru');

        for ($i = 0; $i < 10; $i++) {
            $c->set('password', 'wrong')->call('login')->assertHasErrors('email');
        }
        $c->assertSee('Неверная почта или пароль');

        // Лимит попыток: даже верный пароль не пускает
        $c->set('password', 'sunnyday2026')->call('login')->assertSee('Слишком много попыток');
        $this->assertGuest();
    }

    public function test_blocked_account(): void
    {
        $this->user(User::ROLE_STUDENT, ['is_blocked' => true]);

        Livewire::test(Login::class)
            ->set('email', 'student@mail.ru')
            ->set('password', 'sunnyday2026')
            ->call('login')
            ->assertNoRedirect()
            ->assertSee('Доступ к кабинету приостановлен')
            ->assertSee('student@mail.ru')
            ->call('back')
            ->assertSee('Вход в кабинет');

        $this->assertGuest();
    }

    public function test_old_panel_logins_redirect_here(): void
    {
        $this->get('/admin/login')->assertRedirect(route('login'));
        $this->get('/tutor/login')->assertRedirect(route('login'));
        $this->get('/student/login')->assertRedirect(route('login'));
        $this->get('/tutor/password-reset/request')->assertRedirect(route('password.request'));

        // Гость в закрытом старом кабинете → сразу на общий вход
        $this->get('/tutor/students')->assertRedirect(route('login'));
    }

    public function test_forgot_password_sends_link_and_hides_unknown_emails(): void
    {
        Notification::fake();
        $user = $this->user(User::ROLE_TUTOR);

        $this->get('/forgot-password')->assertOk()->assertSee('Восстановление пароля');

        Livewire::test(ForgotPassword::class)
            ->set('email', 'tutor@mail.ru')
            ->call('send')
            ->assertSee('Проверьте почту')
            ->assertSee('60 минут');

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($n) use ($user) {
            return str_contains($n->toMail($user)->actionUrl, '/reset-password/');
        });

        // Незнакомая почта — тот же ответ, без подсказки, есть ли аккаунт
        Livewire::test(ForgotPassword::class)
            ->set('email', 'nobody@mail.ru')
            ->call('send')
            ->assertSee('Проверьте почту');
    }

    public function test_reset_password_logs_in(): void
    {
        $user = $this->user(User::ROLE_TUTOR);
        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Новый пароль')
            ->assertSee('tutor@mail.ru');

        Livewire::withQueryParams(['email' => $user->email])
            ->test(ResetPassword::class, ['token' => $token])
            ->set('password', 'short')
            ->set('password_confirmation', 'short')
            ->call('save')
            ->assertHasErrors(['password' => 'min'])
            ->set('password', 'englishday26')
            ->set('password_confirmation', 'englishday26')
            ->call('save')
            ->assertRedirect(route('cabinet.teacher.today'));

        $this->assertTrue(Hash::check('englishday26', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_reset_link(): void
    {
        $user = $this->user(User::ROLE_STUDENT);

        Livewire::withQueryParams(['email' => $user->email])
            ->test(ResetPassword::class, ['token' => 'bad-token'])
            ->assertSee('Ссылка устарела')
            ->assertSee('Запросить новую ссылку')
            ->call('save')
            ->assertForbidden();
    }

    public function test_logout_leads_to_login(): void
    {
        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->post(route('logout'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
