<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Account;
use App\Models\User;
use App\Notifications\EmailChanged;
use App\Notifications\EmailVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Почта и пароль (/cabinet/account): смена только через код из письма. */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function user(string $role = User::ROLE_STUDENT, array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'username' => $role . uniqid(),
            'email' => 'old@example.com',
            'password' => Hash::make('old-secret'),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ], $attrs));
    }

    /** Код из последнего письма на адрес. */
    private function codeSentTo(string $email): string
    {
        $code = null;
        Notification::assertSentTo(new AnonymousNotifiable, EmailVerificationCode::class, function ($n, $channels, $notifiable) use ($email, &$code) {
            if ($notifiable->routes['mail'] === $email) {
                $code = $n->code;

                return true;
            }

            return false;
        });

        return $code;
    }

    public function test_page_opens_for_every_role_and_guest_is_redirected(): void
    {
        $this->get(route('cabinet.account'))->assertRedirect();

        foreach ([User::ROLE_STUDENT, User::ROLE_ADMIN] as $role) {
            $this->actingAs($this->user($role, ['email' => $role . '@example.com']))
                ->get(route('cabinet.account'))
                ->assertOk()
                ->assertSee('Почта и пароль')
                ->assertSee($role . '@example.com');
        }
    }

    public function test_teacher_gets_it_as_profile_tab(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, ['email' => 'teacher@example.com']);

        $this->actingAs($teacher)->get(route('cabinet.account'))
            ->assertRedirect(route('cabinet.teacher.profile', ['tab' => 'account']));

        $this->actingAs($teacher)->get(route('cabinet.teacher.profile', ['tab' => 'account']))
            ->assertOk()
            ->assertSee('teacher@example.com');
    }

    public function test_email_changes_after_password_and_code_from_new_address(): void
    {
        $user = $this->user();

        $page = Livewire::actingAs($user)->test(Account::class)
            ->call('open', 'email')
            ->set('newEmail', 'New@Example.com')
            ->set('currentPassword', 'wrong-pass')
            ->call('sendCode')
            ->assertHasErrors(['currentPassword'])
            ->set('currentPassword', 'old-secret')
            ->call('sendCode')
            ->assertHasNoErrors()
            ->assertSet('step', 'code')
            ->assertSee('new@example.com');

        $code = $this->codeSentTo('new@example.com');
        $this->assertSame('old@example.com', $user->fresh()->email);

        $page->set('verification_code', $code === '000000' ? '111111' : '000000')
            ->call('confirm')
            ->assertHasErrors(['verification_code'])
            ->set('verification_code', $code)
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSet('editing', null)
            ->assertDispatched('toast');

        $this->assertSame('new@example.com', $user->fresh()->email);
        Notification::assertSentTo(new AnonymousNotifiable, EmailChanged::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'old@example.com');
    }

    public function test_email_must_be_free_and_different(): void
    {
        $this->user(User::ROLE_STUDENT, ['email' => 'taken@example.com']);
        $user = $this->user();

        Livewire::actingAs($user)->test(Account::class)
            ->call('open', 'email')
            ->set('currentPassword', 'old-secret')
            ->set('newEmail', 'taken@example.com')
            ->call('sendCode')
            ->assertHasErrors(['newEmail' => 'unique'])
            ->set('newEmail', 'old@example.com')
            ->call('sendCode')
            ->assertHasErrors(['newEmail']);

        Notification::assertNothingSent();
    }

    public function test_password_changes_after_code_from_current_email(): void
    {
        $user = $this->user(User::ROLE_STUDENT);

        $page = Livewire::actingAs($user)->test(Account::class)
            ->call('open', 'password')
            ->set('newPassword', 'short')
            ->call('sendCode')
            ->assertHasErrors(['newPassword' => 'min'])
            ->set('newPassword', 'new-secret-1')
            ->call('sendCode')
            ->assertHasNoErrors()
            ->assertSet('step', 'code')
            ->assertSet('newPassword', '');

        $this->assertTrue(Hash::check('old-secret', $user->fresh()->password));

        $page->set('verification_code', $this->codeSentTo('old@example.com'))
            ->call('confirm')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('new-secret-1', $user->fresh()->password));
    }

    public function test_code_burns_after_five_wrong_attempts(): void
    {
        $user = $this->user();

        $page = Livewire::actingAs($user)->test(Account::class)
            ->call('open', 'password')
            ->set('newPassword', 'new-secret-1')
            ->call('sendCode');
        $code = $this->codeSentTo('old@example.com');
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $page->set('verification_code', $wrong)->call('confirm');
        }
        $page->assertHasErrors(['code_expired'])->assertSet('step', 'form')
            ->set('verification_code', $code)
            ->call('confirm')
            ->assertHasErrors(['code_expired']);

        $this->assertTrue(Hash::check('old-secret', $user->fresh()->password));
    }

    public function test_profiles_no_longer_change_email_or_password(): void
    {
        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->get(route('cabinet.teacher.profile'))
            ->assertOk()
            ->assertSee('?tab=account', false)
            ->assertDontSee('Вход в кабинет')
            ->assertDontSee('Новый пароль');
    }
}
