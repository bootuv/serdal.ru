<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\RegisterInvitedStudent;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use App\Notifications\NewTeacher;
use App\Notifications\StudentAcceptedInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/** Регистрация ученика по ссылке-приглашению учителя (/register/invite). */
class AuthInviteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function tutor(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_TUTOR,
            'first_name' => 'Мария',
            'last_name' => 'Соколова',
            'middle_name' => 'Андреевна',
            'username' => 'tutor' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);
    }

    private function inviteUrl(User $teacher): string
    {
        return URL::signedRoute('student.invitation', ['teacher' => $teacher->id]);
    }

    /**
     * Компонент по ссылке-приглашению. Livewire::test открывает компонент по служебному адресу, поэтому подпись
     * здесь принимаем как валидную; саму проверку подписи покрывают HTTP-тесты ниже.
     */
    private function invitePage(User $teacher): Testable
    {
        $url = \Mockery::mock(app('url'));
        $url->shouldReceive('hasValidSignature')->andReturnTrue();
        URL::swap($url);

        return Livewire::withQueryParams(['teacher' => $teacher->id])->test(RegisterInvitedStudent::class);
    }

    private function fill(Testable $c, array $overrides = []): Testable
    {
        $values = $overrides + [
            'last_name' => 'Смирнова',
            'first_name' => 'Алина',
            'middle_name' => 'Сергеевна',
            'phone' => '',
            'email' => 'alina@mail.ru',
            'password' => 'englishday',
            'password_confirmation' => 'englishday',
            'agree' => true,
        ];
        foreach ($values as $key => $value) {
            $c->set($key, $value);
        }

        return $c;
    }

    private function sentCode(): string
    {
        $code = null;
        Notification::assertSentOnDemand(EmailVerificationCode::class, function (EmailVerificationCode $n, array $channels, AnonymousNotifiable $notifiable) use (&$code) {
            $code = $n->code;

            return $notifiable->routes['mail'] === 'alina@mail.ru';
        });

        return $code;
    }

    public function test_page_opens_by_signed_link(): void
    {
        $teacher = $this->tutor();

        $this->get($this->inviteUrl($teacher))
            ->assertOk()
            ->assertSee('Мария Соколова приглашает вас заниматься')
            ->assertSee('Получить код на почту')
            ->assertSee(route('login'), false);
    }

    public function test_invalid_signature_is_forbidden(): void
    {
        $teacher = $this->tutor();

        $this->get('/register/invite?teacher=' . $teacher->id)->assertForbidden();
        $this->get($this->inviteUrl($teacher) . 'x')->assertForbidden();
    }

    public function test_register_by_code(): void
    {
        $teacher = $this->tutor();

        $c = $this->fill($this->invitePage($teacher))
            ->call('register')
            ->assertHasNoErrors()
            ->assertSet('step', 2)
            ->assertSee('Проверьте почту')
            ->assertSee('alina@mail.ru');

        $code = $this->sentCode();
        $this->assertSame(0, User::where('email', 'alina@mail.ru')->count());

        $c->set('verification_code', $code)
            ->call('verifyAndRegister')
            ->assertHasNoErrors()
            ->assertRedirect(route('cabinet.student.home'));

        $student = User::where('email', 'alina@mail.ru')->firstOrFail();
        $this->assertSame('student', $student->role);
        $this->assertNotNull($student->email_verified_at);
        $this->assertTrue(Hash::check('englishday', $student->password));
        $this->assertAuthenticatedAs($student);
        $this->assertTrue($teacher->students()->whereKey($student->id)->exists());
        Notification::assertSentTo($teacher, StudentAcceptedInvite::class);
        Notification::assertSentTo($student, NewTeacher::class);
        $this->assertNull(session('registration_data'));
    }

    public function test_form_validation(): void
    {
        $teacher = $this->tutor();

        // Без согласия, пароли не совпадают
        $this->fill($this->invitePage($teacher), ['agree' => false, 'password_confirmation' => 'other-pass', 'middle_name' => ''])
            ->call('register')
            ->assertHasErrors(['agree' => 'accepted', 'password' => 'confirmed', 'middle_name' => 'required'])
            ->assertSee('Отметьте согласие, чтобы продолжить')
            ->assertSee('Пароли не совпадают')
            ->assertSet('step', 1);

        Notification::assertNothingSent();
    }

    public function test_taken_email_offers_login(): void
    {
        $teacher = $this->tutor();
        User::factory()->create(['email' => 'alina@mail.ru', 'role' => User::ROLE_STUDENT, 'username' => 'st' . uniqid()]);

        $this->fill($this->invitePage($teacher))
            ->call('register')
            ->assertHasErrors('email')
            ->assertSet('emailTaken', true)
            ->assertSee('Этот email уже зарегистрирован.')
            ->assertSee('Войти с этим email')
            ->assertSet('step', 1);
    }

    public function test_wrong_code_and_resend(): void
    {
        $teacher = $this->tutor();

        $c = $this->fill($this->invitePage($teacher))->call('register');
        $first = $this->sentCode();

        $c->set('verification_code', $first === '000000' ? '111111' : '000000')
            ->call('verifyAndRegister')
            ->assertHasErrors('verification_code')
            ->assertSee('Код не подходит');

        $c->set('verification_code', '12')
            ->call('verifyAndRegister')
            ->assertHasErrors('verification_code')
            ->assertSee('Введите все 6 цифр');

        // Сразу повторно — нельзя, через минуту — можно: бейдж и новый код
        $c->call('resendCode')->assertHasErrors('verification_code');
        $this->travel(61)->seconds();
        $c->call('resendCode')
            ->assertSet('codeResent', true)
            ->assertSee('Новый код отправлен')
            ->assertDontSee('Отправить код ещё раз');
        Notification::assertSentOnDemandTimes(EmailVerificationCode::class, 2);
        $newCode = session('registration_data.verification_code');

        $c->set('verification_code', $newCode)
            ->call('verifyAndRegister')
            ->assertRedirect(route('cabinet.student.home'));
        $this->assertTrue(User::where('email', 'alina@mail.ru')->exists());
    }

    public function test_expired_code_returns_to_form(): void
    {
        $teacher = $this->tutor();

        $c = $this->fill($this->invitePage($teacher))->call('register');
        $code = $this->sentCode();

        $this->travel(31)->minutes();

        $c->set('verification_code', $code)
            ->call('verifyAndRegister')
            ->assertSet('step', 1)
            ->assertSee('Код устарел');
        $this->assertFalse(User::where('email', 'alina@mail.ru')->exists());
    }

    public function test_back_to_form_keeps_data(): void
    {
        $teacher = $this->tutor();

        $this->fill($this->invitePage($teacher))
            ->call('register')
            ->call('backToForm')
            ->assertSet('step', 1)
            ->assertSet('email', 'alina@mail.ru')
            ->assertSee('Получить код на почту');
    }

    public function test_signed_in_student_is_attached_to_teacher(): void
    {
        $teacher = $this->tutor();
        $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'username' => 'st' . uniqid(), 'is_active' => true, 'is_blocked' => false]);

        $this->actingAs($student)
            ->get($this->inviteUrl($teacher))
            ->assertRedirect(route('cabinet.student.home'));

        $this->assertTrue($teacher->students()->whereKey($student->id)->exists());
        Notification::assertSentTo($teacher, StudentAcceptedInvite::class);
    }

    public function test_code_burns_after_five_wrong_attempts_and_password_is_not_kept_in_session(): void
    {
        $c = $this->fill($this->invitePage($this->tutor()))->call('register');
        $code = $this->sentCode();
        $this->assertArrayNotHasKey('password', session('registration_data'));

        $wrong = $code === '000000' ? '111111' : '000000';
        foreach (range(1, 5) as $i) {
            $c->set('verification_code', $wrong)->call('verifyAndRegister');
        }
        $c->assertSee('Слишком много неверных попыток');

        // Даже верный код больше не подходит
        $c->set('verification_code', $code)->call('verifyAndRegister')->assertSee('Код устарел');
        $this->assertFalse(User::where('email', 'alina@mail.ru')->exists());
    }

    public function test_teacher_opening_invite_does_not_become_student(): void
    {
        $teacher = $this->tutor();
        $other = $this->tutor();

        $this->actingAs($other)->get($this->inviteUrl($teacher))
            ->assertRedirect(route('cabinet.teacher.today'))
            ->assertSessionHas('error');
        $this->assertFalse($teacher->students()->whereKey($other->id)->exists());
    }
}
