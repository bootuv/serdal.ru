<?php

namespace Tests\Feature;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Cabinet\Account;
use App\Livewire\Cabinet\Teacher\Students;
use App\Livewire\RegisterInvitedStudent;
use App\Models\User;
use App\Notifications\SubscriptionAutoRenewFailed;
use App\Support\MailDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/**
 * Сервис почты отказал (дневной лимит Postbox): вместо «Что-то пошло не так» — сообщение
 * «Извините, технические неполадки», действие не ломается.
 */
class MailFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // Основной канал почты отвечает как Postbox при исчерпанном лимите
        Mail::extend('quota', fn () => new class extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                throw new UnexpectedResponseException('Expected response code "250" but got code "550", with message "550 5.4.5 Daily sending quota exceeded."', 550);
            }

            public function __toString(): string
            {
                return 'quota://';
            }
        });
        config(['mail.mailers.quota' => ['transport' => 'quota'], 'mail.default' => 'quota']);
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

    public function test_invite_registration_stays_on_form_with_message(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $url = \Mockery::mock(app('url'));
        $url->shouldReceive('hasValidSignature')->andReturnTrue();
        URL::swap($url);

        Livewire::withQueryParams(['teacher' => $teacher->id])->test(RegisterInvitedStudent::class)
            ->set('last_name', 'Смирнова')
            ->set('first_name', 'Алина')
            ->set('email', 'alina@mail.ru')
            ->set('password', 'englishday')
            ->set('password_confirmation', 'englishday')
            ->set('agree', true)
            ->call('register')
            ->assertSet('step', 1)
            ->assertHasErrors(['mail_failed'])
            ->assertSee(MailDelivery::FAILED);

        $this->assertNull(session('registration_data'));
        $this->assertSame(0, RateLimiter::attempts('invite-code:127.0.0.1'), 'неудачная отправка не съедает попытки');
        $this->assertFalse(User::where('email', 'alina@mail.ru')->exists());
    }

    public function test_invite_resend_keeps_previous_code(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $url = \Mockery::mock(app('url'));
        $url->shouldReceive('hasValidSignature')->andReturnTrue();
        URL::swap($url);

        session()->put('registration_data', [
            'first_name' => 'Алина', 'last_name' => 'Смирнова', 'middle_name' => null, 'email' => 'alina@mail.ru', 'phone' => null,
            'password_hash' => Hash::make('englishday'), 'teacher_id' => $teacher->id, 'verification_code' => '123456',
            'expires_at' => now()->addMinutes(30), 'attempts' => 0, 'sent_at' => now()->subMinutes(5)->toIso8601String(),
        ]);

        Livewire::withQueryParams(['teacher' => $teacher->id])->test(RegisterInvitedStudent::class)
            ->set('step', 2)
            ->call('resendCode')
            ->assertHasErrors(['verification_code'])
            ->assertSee(MailDelivery::FAILED)
            ->assertSet('codeResent', false);

        $this->assertSame('123456', session('registration_data')['verification_code']);
    }

    public function test_account_email_change_shows_message(): void
    {
        $user = $this->user(User::ROLE_STUDENT, ['email' => 'old@example.com', 'password' => Hash::make('old-secret')]);

        Livewire::actingAs($user)->test(Account::class)
            ->call('open', 'email')
            ->set('newEmail', 'new@example.com')
            ->set('currentPassword', 'old-secret')
            ->call('sendCode')
            ->assertHasErrors(['newEmail'])
            ->assertSee(MailDelivery::FAILED)
            ->assertSet('step', 'form');

        $this->assertNull(session('account_change'), 'без письма смена не ждёт кода');
    }

    public function test_forgot_password_shows_message(): void
    {
        $this->user(User::ROLE_TUTOR, ['email' => 'tutor@mail.ru']);

        Livewire::test(ForgotPassword::class)
            ->set('email', 'tutor@mail.ru')
            ->call('send')
            ->assertHasErrors(['email'])
            ->assertSee(MailDelivery::FAILED)
            ->assertSet('sent', false);
    }

    public function test_teacher_invite_by_email_shows_message(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);

        Livewire::actingAs($teacher)->test(Students::class)
            ->call('openInvite', 'link')
            ->set('inviteEmail', 'new.student@example.com')
            ->call('sendInvite')
            ->assertHasErrors(['inviteEmail'])
            ->assertSee(MailDelivery::FAILED)
            ->assertSet('inviteOpen', true);
    }

    public function test_notification_with_mail_still_reaches_cabinet(): void
    {
        $user = $this->user(User::ROLE_TUTOR, ['email' => 'tutor@mail.ru']);

        $user->notifyNow(new SubscriptionAutoRenewFailed('Профи'), ['database', 'mail']);

        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_livewire_action_gets_toast_instead_of_error(): void
    {
        Livewire::test(new class extends Component {
            public bool $done = false;

            public function go(): void
            {
                Mail::raw('Текст', fn ($m) => $m->to('a@example.com'));
                $this->done = true;
            }

            public function render(): string
            {
                return '<div></div>';
            }
        })
            ->call('go')
            ->assertOk()
            ->assertDispatched('toast', message: MailDelivery::FAILED, tone: 'danger');
    }

    public function test_page_shows_apology(): void
    {
        Route::get('/_test/mail', fn () => Mail::raw('Текст', fn ($m) => $m->to('a@example.com')));

        $this->get('/_test/mail')
            ->assertStatus(503)
            ->assertSee('Извините, технические неполадки')
            ->assertSee('Пожалуйста, вернитесь позже.');
    }
}
